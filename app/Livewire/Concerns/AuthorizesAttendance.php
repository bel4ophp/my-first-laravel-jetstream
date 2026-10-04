<?php

namespace App\Livewire\Concerns;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

/**
 * Shared authorisation helpers and permission flags for the attendance
 * components (the calendar and its day panel).
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

    #[Computed]
    public function canViewTimeEntries(): bool
    {
        $user = $this->currentUser();

        return $user !== null && $this->allows('view', new TimeEntry(['user_id' => $user->id]));
    }

    #[Computed]
    public function canExportAttendance(): bool
    {
        return $this->allows('export', TimeEntry::class);
    }
}
