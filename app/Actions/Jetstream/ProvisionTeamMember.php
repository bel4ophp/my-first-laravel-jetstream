<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Create an account straight onto a team and let the person set their own
 * password.
 *
 * The one place a new member is provisioned: the admin's Users screen, the team
 * invite flow (for an address with no account yet) and, later, the API all go
 * through here. Callers authorize and validate first — which roles may be
 * handed out is TeamRole::assignableBy()'s call, not this action's.
 */
class ProvisionTeamMember
{
    public function provision(Team $team, string $name, string $email, string $role): User
    {
        // One transaction, so a failed team attach can't leave an account that
        // belongs to no team.
        $member = DB::transaction(function () use ($team, $name, $email, $role) {
            $member = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);

            $team->users()->attach($member, ['role' => $role]);
            $member->switchTeam($team);

            return $member;
        });

        // Outside the transaction: a mail failure must not roll back the member.
        $member->sendPasswordResetNotification(Password::createToken($member));

        return $member;
    }
}
