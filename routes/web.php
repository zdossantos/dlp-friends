<?php

use App\Enums\SocialProvider;
use App\Http\Controllers\Admin\AvatarController;
use App\Http\Controllers\Admin\AvatarOrderController;
use App\Http\Controllers\Admin\AvatarStatusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InterestController;
use App\Http\Controllers\Admin\InterestOrderController;
use App\Http\Controllers\Admin\InterestSettingController;
use App\Http\Controllers\Admin\InterestStatusController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\MemberConversationController;
use App\Http\Controllers\Admin\ProductOnboardingController as AdminProductOnboardingController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SocialRegistrationController;
use App\Http\Controllers\AvatarImageController;
use App\Http\Controllers\BlockMemberController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationIndexController;
use App\Http\Controllers\ConversationReadController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductOnboardingController;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\PublicMatchingController;
use App\Http\Controllers\PublicMemberProfileController;
use App\Http\Controllers\SwipeController;
use App\Http\Controllers\UnblockMemberController;
use App\Support\PublicUrls;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicLandingController::class, 'redirect'])->name('home');
Route::get('matching', [PublicMatchingController::class, 'redirect'])->name('matching.redirect');
Route::get('fr/matching', [PublicMatchingController::class, 'show'])->defaults('locale', 'fr')->name('matching.show.fr');
Route::get('en/matching', [PublicMatchingController::class, 'show'])->defaults('locale', 'en')->name('matching.show.en');
Route::get('fr/conditions-generales-utilisation', [LegalDocumentController::class, 'terms'])->defaults('locale', 'fr')->name('legal.terms.fr');
Route::get('en/terms-of-use', [LegalDocumentController::class, 'terms'])->defaults('locale', 'en')->name('legal.terms.en');
Route::get('fr/politique-confidentialite', [LegalDocumentController::class, 'privacy'])->defaults('locale', 'fr')->name('legal.privacy.fr');
Route::get('en/privacy-policy', [LegalDocumentController::class, 'privacy'])->defaults('locale', 'en')->name('legal.privacy.en');
Route::get('{locale}', [PublicLandingController::class, 'show'])
    ->whereIn('locale', ['fr', 'en'])
    ->name('landing.show');
Route::get('sitemap.xml', function () {
    return response()
        ->view('sitemap', ['groups' => [
            ['fr' => PublicUrls::landing('fr'), 'en' => PublicUrls::landing('en')],
            ['fr' => PublicUrls::matching('fr'), 'en' => PublicUrls::matching('en')],
            ['fr' => PublicUrls::terms('fr'), 'en' => PublicUrls::terms('en')],
            ['fr' => PublicUrls::privacy('fr'), 'en' => PublicUrls::privacy('en')],
        ]])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::get('robots.txt', function () {
    return response(implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Sitemap: '.PublicUrls::sitemap(),
        '',
    ]), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');
Route::patch('locale', LocaleController::class)->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', array_column(SocialProvider::cases(), 'value'))
        ->middleware('throttle:10,1')
        ->name('social.redirect');
    Route::get('auth/google/callback', [SocialAuthController::class, 'callback'])
        ->defaults('provider', SocialProvider::Google->value)
        ->middleware('throttle:10,1')
        ->name('social.callback.google');
    Route::get('auth/social/complete', [SocialRegistrationController::class, 'create'])
        ->name('social.registration.create');
    Route::post('auth/social/complete', [SocialRegistrationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('social.registration.store');
});

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
        Route::post('onboarding/complete', [ProductOnboardingController::class, 'complete'])
            ->name('onboarding.complete');

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::get('onboarding', [AdminProductOnboardingController::class, 'index'])
                ->name('onboarding.index');
            Route::patch('onboarding', [AdminProductOnboardingController::class, 'update'])
                ->name('onboarding.update');
        });

        Route::middleware('onboarding.complete')->group(function () {
            Route::get('profile', [MemberProfileController::class, 'show'])
                ->name('member-profile.show');
            Route::get('profile/edit', [MemberProfileController::class, 'edit'])
                ->name('member-profile.edit');
            Route::patch('profile', [MemberProfileController::class, 'update'])
                ->name('member-profile.update');

            Route::get('members/{member}', PublicMemberProfileController::class)
                ->name('members.show');
            Route::post('members/{member}/block', BlockMemberController::class)
                ->name('members.block');
            Route::delete('members/{member}/block', UnblockMemberController::class)
                ->name('members.unblock');

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
                Route::get('members', [AdminMemberController::class, 'index'])
                    ->name('members.index');
                Route::delete('members/{member}', [AdminMemberController::class, 'destroy'])
                    ->name('members.destroy');
                Route::post('members/{member}/conversation', MemberConversationController::class)
                    ->name('members.conversation.store');
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
});

require __DIR__.'/settings.php';
