<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Shared authorisation helpers for the attendance components.
 *
 * Kept in one place because `allows()` encodes a security invariant — signed in
 * *and* permitted — that must not drift between the calendar and its day panel.
 */
trait AuthorizesAttendance
{
    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * A bare Gate::check() would pass for a guest whose policy ignores its user
     * argument, so signed-in status is checked alongside it.
     */
    protected function allows(string $ability, mixed $argument): bool
    {
        return $this->currentUser() !== null && Gate::check($ability, $argument);
    }
}
