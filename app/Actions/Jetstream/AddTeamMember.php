<?php

namespace App\Actions\Jetstream;

use App\Enums\TeamRole;
use App\Rules\BelongsToNoOtherTeam;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddTeamMember implements AddsTeamMembers
{
    /**
     * Add a new team member to the given team.
     */
    public function add(User $user, Team $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        $newTeamMember = Jetstream::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($team, $newTeamMember);

        $team->users()->attach(
            $newTeamMember, ['role' => $role]
        );

        TeamMemberAdded::dispatch($team, $newTeamMember);
    }

    /**
     * Validate the add member operation.
     */
    protected function validate(Team $team, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($team), [
            'email.exists' => __('We were unable to find a registered user with this email address.'),
        ])
            ->after($this->ensureUserIsNotAlreadyOnTeam($team, $email))
            ->after($this->ensureTeamHasOnlyOneManager($team, $role))
            ->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a team member.
     *
     * @return array<string, Rule|array|string>
     */
    protected function rules(Team $team): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users', new BelongsToNoOtherTeam($team)],
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
