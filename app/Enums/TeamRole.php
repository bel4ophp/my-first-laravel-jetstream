<?php

namespace App\Enums;

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
     * The roles that are actually assignable to a team member.
     *
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return [self::Admin->value, self::Manager->value, self::Employee->value];
    }
}
