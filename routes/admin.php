<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyStatusController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PlatformSettingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/companies');

Route::resource('companies', CompanyController::class)->except(['destroy']);
Route::put('companies/{company}/status', [CompanyStatusController::class, 'update'])->name('companies.status.update');
Route::post('companies/{company}/impersonate', [ImpersonationController::class, 'store'])->name('companies.impersonate');

Route::get('settings', [PlatformSettingController::class, 'edit'])->name('settings.edit');
Route::put('settings', [PlatformSettingController::class, 'update'])->name('settings.update');
