<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\SwipeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', 'social'])->group(function () {
    Route::get('app', LandingController::class)->name('app');

    Route::get('profile/create', [MemberProfileController::class, 'create'])
        ->name('member-profile.create');
    Route::post('profile', [MemberProfileController::class, 'store'])
        ->name('member-profile.store');

    Route::middleware('profile.complete')->group(function () {
        Route::get('profile', [MemberProfileController::class, 'show'])
            ->name('member-profile.show');
        Route::get('profile/edit', [MemberProfileController::class, 'edit'])
            ->name('member-profile.edit');
        Route::patch('profile', [MemberProfileController::class, 'update'])
            ->name('member-profile.update');

        Route::get('discover', DiscoveryController::class)
            ->name('discovery.index');
        Route::post('discover/{target}/swipe', SwipeController::class)
            ->name('discovery.swipe');

        Route::get('dashboard', DashboardController::class)
            ->middleware('role:admin')
            ->name('dashboard');
    });
});

require __DIR__.'/settings.php';
