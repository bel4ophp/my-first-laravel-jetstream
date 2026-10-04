<?php

namespace App\Actions\Jetstream;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    /**
     * Update the role for the given team member.
     *
     * Jetstream's action accepts any role that exists and lets a user change
     * their own. Here nobody changes their own role, only assignable roles are
     * accepted (never the team-level Admin role), and a team keeps a single
     * manager because leave approval routes to them.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     * @param  int  $teamMemberId
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        Gate::forUser($user)->authorize('updateTeamMember', $team);

        if ((int) $teamMemberId === $user->id) {
            throw new AuthorizationException(__('You may not change your own role.'));
        }

        Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::in(TeamRole::assignableBy($user, $team))],
        ])->after(function ($validator) use ($role, $team, $teamMemberId) {
            $validator->errors()->addIf(
                $role === TeamRole::Manager->value && $team instanceof Team && $team->hasManagerBesides($teamMemberId),
                'role',
                __('Only one manager is allowed per team.')
            );
        })->validate();

        parent::update($user, $team, $teamMemberId, $role);
    }
}
