<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserDataExportController;
use App\Http\Controllers\Settings\WebPushSubscriptionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'social', 'role:user', 'profile.complete', 'onboarding.complete'])->group(function () {
    Route::redirect('settings', '/settings/account');

    Route::get('settings/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('settings/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('settings/account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::post('settings/data-export', [UserDataExportController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('data-export.store');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('notification-preferences.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('notification-preferences.update');
    Route::post('settings/notifications/devices', [WebPushSubscriptionController::class, 'store'])
        ->name('notification-devices.store');
    Route::delete('settings/notifications/devices/{uuid}', [WebPushSubscriptionController::class, 'destroy'])
        ->name('notification-devices.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
