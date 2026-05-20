<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;
use App\Rules\TeamManager;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the given team.
     */
    public function invite(User $user, Team $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($user, $team, $email, $role);

        try {
            Jetstream::findUserByEmailOrFail($email);

            InvitingTeamMember::dispatch($team, $email, $role);

            $invitation = $team->teamInvitations()->create([
                'email' => $email,
                'role' => $role,
            ]);

            Log::error("User with email {$email} found. Sending invitation.");

            Mail::to($email)->send(new TeamInvitation($invitation));
        } catch (\Throwable $th) {
            Log::error("User with email {$email} not found. Creating new user and sending password reset link.");
            $newTeamMember = User::create([
                'name' => $email,
                'email' => $email,
                'password' => bcrypt(str()->random(16)), // random password
            ]);

            // Assign user to selected team if provided
            $team->users()->attach($newTeamMember, ['role' => $role]);
            $newTeamMember->switchTeam($team);

            // Generate password reset token
            $token = Password::createToken($newTeamMember);

            // Send password reset notification
            $newTeamMember->sendPasswordResetNotification($token);
        }
    }

    /**
     * Validate the invite member operation.
     */
    protected function validate(User $user, Team $team, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($team), [
            'email.unique' => __('This user has already been invited to the team.'),
        ])
            ->after($this->ensureUserIsNotAlreadyOnTeam($team, $email))
            ->after($this->ensureUserIsNotAlreadyOnAnyTeam($email))
            ->after($this->ensureOnlyOneManagerPerTeamUnlessAdmin($team, $role, $user))
            ->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(Team $team): array
    {
        return array_filter([
            'email' => [
                'required',
                'email',
                Rule::unique(Jetstream::teamInvitationModel())->where(function (Builder $query) use ($team) {
                    $query->where('team_id', $team->id);
                }),
                new TeamManager,
            ],
            'role' => Jetstream::hasRoles() ? ['required', 'string', new Role] : null,
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

    protected function ensureUserIsNotAlreadyOnAnyTeam(string $email): Closure
    {
        return function ($validator) use ($email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                return;
            }

            // Checks if user belongs to any team
            if ($user->teams()->exists()) {
                $validator->errors()->add(
                    'email',
                    __('This user already belongs to another team.')
                );
            }
        };
    }

    /**
     * Ensure that only one manager is allowed per team unless the user is the team admin.
     */
    protected function ensureOnlyOneManagerPerTeamUnlessAdmin(Team $team, ?string $role, User $user): Closure
    {
        return function ($validator) use ($team, $role, $user) {
            if ($role === 'manager') {
                $hasManager = $team->users()->wherePivot('role', 'manager')->exists();
                if ($hasManager) {
                    $validator->errors()->add('role', __('Only one manager is allowed per team.'));
                }
            }
        };
    }
}
