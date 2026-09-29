<?php

namespace App\Actions\Jetstream;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    /**
     * Update the role for the given team member.
     *
     * Jetstream's action validates the role key but not how many members may
     * hold it. Leave approval routes to a team's single manager, so promoting a
     * second one would leave requests with an ambiguous approver.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     * @param  int  $teamMemberId
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        if ($role === TeamRole::Manager->value && $team instanceof Team && $team->hasManagerBesides($teamMemberId)) {
            Validator::make([], [])->after(function ($validator) {
                $validator->errors()->add('role', __('Only one manager is allowed per team.'));
            })->validate();
        }

        parent::update($user, $team, $teamMemberId, $role);
    }
}
