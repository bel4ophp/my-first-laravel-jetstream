<?php

use App\Http\Controllers\AttendanceExportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::middleware('can:viewAny,App\Models\User')->group(function () {
        Route::get('/users', [App\Http\Controllers\UsersController::class, 'index'])->name('users.index');
        Route::get('/users/create', [App\Http\Controllers\UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [App\Http\Controllers\UsersController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [App\Http\Controllers\UsersController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [App\Http\Controllers\UsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [App\Http\Controllers\UsersController::class, 'destroy'])->name('users.destroy');
    });

    // Unified leave page — the Request tab is available to all roles, the
    // Validation tab is gated per-policy inside the view.
    Route::get('/leave', fn () => view('leave.index'))->name('leave.index');

    // Redirects preserve links from previously sent notification emails.
    Route::redirect('/leave-requests', '/leave');
    Route::redirect('/leave-approvals', '/leave?tab=validation');
});

Route::middleware(['auth', 'verified', 'can:viewAny,App\Models\TimeEntry'])
    ->prefix('reports')
    ->name('reports.attendance.')
    ->group(function () {

        // The calendar page — Livewire renders the component
        Route::get('attendance', fn() => view('reports.attendance'))
            ->name('index');

        // CSV export — additionally gated to admin + manager only
        Route::get('attendance/export', [AttendanceExportController::class, 'export'])
            ->middleware('can:export,App\Models\TimeEntry')
            ->name('export');
    });
