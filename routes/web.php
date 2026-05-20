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

    Route::get('/users', [App\Http\Controllers\UsersController::class, 'index'])->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\UsersController::class, 'create'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\UsersController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\UsersController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\UsersController::class, 'destroy'])->name('users.destroy');
});

Route::middleware(['auth', 'verified', 'can:view-attendance'])
    ->prefix('reports')
    ->name('reports.attendance.')
    ->group(function () {

        // The calendar page — Livewire renders the component
        Route::get('attendance', fn() => view('reports.attendance'))
            ->name('index');

        // CSV export (plain controller, no Livewire needed)
        Route::get('attendance/export', [AttendanceExportController::class, 'export'])
            ->name('export');
    });
