<?php

use App\Http\Controllers\Admin\AvatarController;
use App\Http\Controllers\Admin\AvatarOrderController;
use App\Http\Controllers\Admin\AvatarStatusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InterestController;
use App\Http\Controllers\Admin\InterestOrderController;
use App\Http\Controllers\Admin\InterestSettingController;
use App\Http\Controllers\Admin\InterestStatusController;
use App\Http\Controllers\AvatarImageController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationIndexController;
use App\Http\Controllers\ConversationReadController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductOnboardingController;
use App\Http\Controllers\SwipeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::patch('locale', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified', 'social'])->group(function () {
    Route::get('avatars/{avatar}/image', AvatarImageController::class)
        ->name('avatars.image');

    Route::get('app', LandingController::class)->name('app');

    Route::get('profile/create', [MemberProfileController::class, 'create'])
        ->name('member-profile.create');
    Route::post('profile', [MemberProfileController::class, 'store'])
        ->name('member-profile.store');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::resource('avatars', AvatarController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::patch('avatars/{avatar}/status', AvatarStatusController::class)
            ->name('avatars.status');
        Route::patch('avatars/{avatar}/move', AvatarOrderController::class)
            ->name('avatars.move');
    });

    Route::middleware('profile.complete')->group(function () {
        Route::get('onboarding', [ProductOnboardingController::class, 'show'])
            ->name('onboarding.show');
        Route::patch('onboarding', [ProductOnboardingController::class, 'advance'])
            ->name('onboarding.advance');
        Route::post('onboarding/skip', [ProductOnboardingController::class, 'skip'])
            ->name('onboarding.skip');
        Route::post('onboarding/restart', [ProductOnboardingController::class, 'restart'])
            ->name('onboarding.restart');
        Route::post('onboarding/complete', [ProductOnboardingController::class, 'complete'])
            ->name('onboarding.complete');

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

        Route::get('conversations', ConversationIndexController::class)
            ->name('conversations.index');
        Route::get('conversations/{conversation}', ConversationController::class)
            ->name('conversations.show');
        Route::post('conversations/{conversation}/messages', MessageController::class)
            ->name('conversations.messages.store');
        Route::post('conversations/{conversation}/read', ConversationReadController::class)
            ->name('conversations.read.store');

        Route::get('dashboard', DashboardController::class)
            ->middleware('role:admin')
            ->name('dashboard');

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::resource('interests', InterestController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::patch('interests/{interest}/status', InterestStatusController::class)
                ->name('interests.status');
            Route::patch('interests/{interest}/move', InterestOrderController::class)
                ->name('interests.move');
            Route::patch('interest-setting', InterestSettingController::class)
                ->name('interest-setting.update');
        });
    });
});

require __DIR__.'/settings.php';
