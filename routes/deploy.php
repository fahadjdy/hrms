<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Deploy URLs
|--------------------------------------------------------------------------
|
| Registered under /deploy, outside the "web" middleware group, so they work
| before the database exists (no session or cache table is needed) and need
| no login. Only repeatable, non-destructive tasks belong here.
|
*/

Route::get('/', [DeployController::class, 'index'])->name('index');
Route::get('run', [DeployController::class, 'all'])->name('run');
Route::get('setup', [DeployController::class, 'setup'])->name('setup');
Route::get('migrate', [DeployController::class, 'migrate'])->name('migrate');
Route::get('seed', [DeployController::class, 'seed'])->name('seed');
Route::get('storage-link', [DeployController::class, 'storageLink'])->name('storage-link');
Route::get('cache', [DeployController::class, 'cache'])->name('cache');
Route::get('clear', [DeployController::class, 'clear'])->name('clear');
