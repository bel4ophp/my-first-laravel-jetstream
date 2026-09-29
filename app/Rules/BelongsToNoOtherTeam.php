<?php

namespace App\Rules;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A user may belong to one team at a time.
 *
 * Leave approval, the role label and the attendance scoping all route off the
 * user's current team, so a second membership would make a user's approver and
 * permissions ambiguous.
 *
 * Applied to both team-joining paths — AddTeamMember and InviteTeamMember —
 * which previously disagreed: adding an existing user allowed a second team
 * while inviting did not.
 *
 * Team owners are unaffected: ownership lives on teams.user_id rather than the
 * team_user pivot, so owning several teams is still allowed.
 */
class BelongsToNoOtherTeam implements ValidationRule
{
    public function __construct(private Team $team) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::where('email', $value)->first();

        if (! $user) {
            return;
        }

        // Membership of *this* team is reported separately, so it is excluded
        // here to avoid two errors describing the same thing.
        $onAnotherTeam = $user->teams()
            ->where('teams.id', '!=', $this->team->id)
            ->exists();

        if ($onAnotherTeam) {
            $fail(__('This user already belongs to another team.'));
        }
    }
}
