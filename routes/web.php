<?php

use App\Http\Controllers\Admin\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Company Admin / HR: every route resolves its tenant from the signed-in user.
Route::middleware(['auth', 'verified', 'tenant'])->group(base_path('routes/tenant.php'));

// Platform owner.
Route::middleware(['auth', 'verified', 'super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

Route::post('impersonation/leave', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')
    ->name('impersonation.leave');

require __DIR__.'/settings.php';
