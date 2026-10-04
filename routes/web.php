<?php

use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\UsersController;
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

    // `can:viewAny` only gates entry to the screen; each action additionally
    // authorizes against the target user via UserPolicy.
    Route::resource('users', UsersController::class)
        ->except('show')
        ->middleware('can:viewAny,App\Models\User');

    // Unified leave page — the Request tab is available to all roles, the
    // Validation tab is gated per-policy inside the view.
    Route::get('/leave', fn () => view('leave.index'))->name('leave.index');

    // Redirects preserve links from previously sent notification emails.
    Route::redirect('/leave-requests', '/leave');
    Route::redirect('/leave-approvals', '/leave?tab=validation');
});

// The same session stack as the rest of the app, so "log out other browser
// sessions" also ends sessions sitting on these pages.
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'can:viewAny,App\Models\TimeEntry'])
    ->prefix('reports')
    ->name('reports.attendance.')
    ->group(function () {

        // The calendar page — Livewire renders the component
        Route::get('attendance', fn () => view('reports.attendance'))
            ->name('index');

        // CSV export — additionally gated to admin + manager only
        Route::get('attendance/export', [AttendanceExportController::class, 'export'])
            ->middleware('can:export,App\Models\TimeEntry')
            ->name('export');
    });
