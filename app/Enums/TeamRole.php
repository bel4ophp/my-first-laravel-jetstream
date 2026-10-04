<?php

namespace App\Enums;

use App\Models\Team;
use App\Models\User;

enum TeamRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';

    /**
     * Jetstream's synthetic role for a team's owner. It is never stored on the
     * team_user pivot; ownsTeam() short-circuits the role lookup instead.
     */
    case Owner = 'owner';

    /**
     * The roles the actor may hand out on the given team — the single source of
     * truth for every add, invite, role change and user-creation path.
     *
     * The global admin assigns managers and employees; a team's manager may
     * only bring in employees. The team-level Admin role carries team create
     * and delete permissions, so it is never assignable.
     *
     * The pivot role is read through teamRole() rather than hasTeamRole(),
     * which answers true for a team's owner whatever role is asked about.
     *
     * @return array<int, string>
     */
    public static function assignableBy(User $actor, ?Team $team = null): array
    {
        if ($actor->is_admin) {
            return [self::Manager->value, self::Employee->value];
        }

        if ($team && $actor->teamRole($team)?->key === self::Manager->value) {
            return [self::Employee->value];
        }

        return [];
    }
}
