<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * NOT IN USE — no validator references this rule, and it would have no effect
 * if one did.
 *
 * It fails when the user manages *any* team. BelongsToNoOtherTeam, which both
 * team-joining paths apply, fails when the user belongs to any other team at
 * all — a strictly wider condition. Anyone who manages a team necessarily
 * belongs to one, so this rule could never be the sole reason a validation
 * failed; it was fully shadowed even while it was wired up.
 *
 * Do not read the name as the one-manager-per-team invariant. That lives in
 * Team::hasManagerBesides() and is enforced by AddTeamMember, InviteTeamMember,
 * UpdateTeamMemberRole and StoreUserRequest.
 *
 * Kept as a reference for the "already a manager elsewhere" check, which would
 * become meaningful again if the one-team-per-user policy were ever relaxed.
 */
class TeamManager implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::where('email', $value)->first();

        if ($user && $user->isTeamManager()) {
            $fail(__('The selected user is already a team manager.'));
        }
    }
}
