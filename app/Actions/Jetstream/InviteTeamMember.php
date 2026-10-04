<?php

namespace App\Actions\Jetstream;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Rules\BelongsToNoOtherTeam;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;

class InviteTeamMember implements InvitesTeamMembers
{
    public function __construct(private ProvisionTeamMember $provisioner) {}

    /**
     * Invite a new team member to the given team.
     */
    public function invite(User $user, Team $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($user, $team, $email, $role);

        // Branch on whether the account exists, rather than on an exception.
        // This previously sat in a try/catch around the whole flow, so a failed
        // mail send or invitation insert was read as "no such user" and fell
        // through to account creation — which then hit the unique-email
        // constraint and surfaced as a 500 with an orphaned invitation row.
        User::where('email', $email)->exists()
            ? $this->sendInvitation($team, $email, $role)
            : $this->provisionMember($team, $email, $role);
    }

    /**
     * Invite someone who already has an account.
     */
    protected function sendInvitation(Team $team, string $email, ?string $role): void
    {
        InvitingTeamMember::dispatch($team, $email, $role);

        $invitation = $team->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]);

        Log::info("Team invitation created for {$email} on team {$team->id}.");

        Mail::to($email)->send(new TeamInvitation($invitation));
    }

    /**
     * Create an account for an unknown address and put them straight on the
     * team, then let them set their own password.
     *
     * No invitation row is written for this path — the membership already
     * exists, so there is nothing left to accept.
     */
    protected function provisionMember(Team $team, string $email, ?string $role): void
    {
        Log::info("No account for {$email}; provisioning one for team {$team->id}.");

        $this->provisioner->provision($team, $this->nameFromEmail($email), $email, $role);
    }

    /**
     * A readable placeholder name until the user sets their own. The address
     * itself used to be stored, so the members list showed raw emails.
     */
    protected function nameFromEmail(string $email): string
    {
        return Str::of($email)
            ->before('@')
            ->replace(['.', '_', '-', '+'], ' ')
            ->squish()
            ->headline()
            ->value();
    }

    /**
     * Validate the invite member operation.
     */
    protected function validate(User $user, Team $team, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($user, $team), [
            'email.unique' => __('This user has already been invited to the team.'),
        ])
            ->after($this->ensureUserIsNotAlreadyOnTeam($team, $email))
            ->after($this->ensureTeamHasOnlyOneManager($team, $role))
            ->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(User $user, Team $team): array
    {
        return array_filter([
            'email' => [
                'required',
                'email',
                Rule::unique(Jetstream::teamInvitationModel())->where(function (Builder $query) use ($team) {
                    $query->where('team_id', $team->id);
                }),
                new BelongsToNoOtherTeam($team),
            ],
            'role' => Jetstream::hasRoles() ? ['required', 'string', Rule::in(TeamRole::assignableBy($user, $team))] : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the team.
     */
    protected function ensureUserIsNotAlreadyOnTeam(Team $team, string $email): Closure
    {
        return function ($validator) use ($team, $email) {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }

    /**
     * Leave approval routes to a team's single manager, so a second one would
     * leave requests with an ambiguous approver.
     */
    protected function ensureTeamHasOnlyOneManager(Team $team, ?string $role): Closure
    {
        return function ($validator) use ($team, $role) {
            $validator->errors()->addIf(
                $role === TeamRole::Manager->value && $team->hasManagerBesides(),
                'role',
                __('Only one manager is allowed per team.')
            );
        };
    }
}
