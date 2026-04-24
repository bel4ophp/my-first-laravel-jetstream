<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Admins pass all checks automatically
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasTeamRole($user->currentTeam, 'admin')) {
            return true; // admin can do everything, skip other checks
        }

        return null; // continue to specific policy method
    }

    /**
     * View users list (admin + manager)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTeamRole($user->currentTeam, 'manager');
    }

    /**
     * View a single user (admin + manager)
     */
    public function view(User $user, User $target): bool
    {
        return $user->hasTeamRole($user->currentTeam, 'manager');
    }

    /**
     * Create a user (admin only — handled by before())
     */
    public function create(User $user): bool
    {
        return $user->hasTeamRole($user->currentTeam, 'manager');
    }

    /**
     * Update a user (admin only — handled by before())
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasTeamRole($user->currentTeam, 'manager');
    }

    /**
     * Delete a user (admin only — handled by before())
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasTeamRole($user->currentTeam, 'manager');
    }
}