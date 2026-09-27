<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
| Admin panel routes. Loaded from routes/web.php (require __DIR__.'/admin.php'),
| so they get the normal "web" middleware group (sessions, CSRF).
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/activity-log', [Admin\ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');

        // @admin-resources (scaffold.php inserts new resource routes above this line)
    });
});
