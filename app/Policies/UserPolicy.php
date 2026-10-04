<?php

namespace App\Policies;

use App\Models\User;
use App\Services\LeaveResetService;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The Users screen is the global admin's alone; managers bring employees in
 * through their team page instead.
 *
 * The admin passes every check through the Gate::before hook in
 * AppServiceProvider, so the Users-screen abilities only have to refuse everyone
 * else — except delete(), which that hook hands back to this policy.
 * manageLeaveBalance() is the one ability a manager can also pass.
 */
class UserPolicy
{
    use HandlesAuthorization;

    public function __construct(private LeaveResetService $resetService) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return false;
    }

    /**
     * Gate::before deliberately lets this check run for the admin too. Admins
     * are never deletable here, the actor included — removing the last admin
     * would lock everyone out of role management.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->is_admin
            && ! $user->is($target)
            && ! $target->is_admin;
    }

    /**
     * Change the member's available leave days: a manager for themselves and
     * their own team's employees — the same people their pool reset covers.
     * The admin passes for anyone through Gate::before.
     */
    public function manageLeaveBalance(User $user, User $member): bool
    {
        return $user->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, 'manage-leave-settings')
            && $this->resetService->scopedUserIds($user)->contains($member->id);
    }
}
