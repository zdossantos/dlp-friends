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
use App\Http\Controllers\Admin\MemberRoleController;
use App\Http\Controllers\Admin\PartnerAnnouncementController as AdminPartnerAnnouncementController;
use App\Http\Controllers\Admin\PartnerAnnouncementDecisionController;
use App\Http\Controllers\Admin\PartnerAnnouncementDispatchController;
use App\Http\Controllers\Admin\PartnerAnnouncementRetryController;
use App\Http\Controllers\Admin\PartnerProfileController as AdminPartnerProfileController;
use App\Http\Controllers\Admin\PartnerProfileDecisionController;
use App\Http\Controllers\Admin\PartnerProfileOrderController;
use App\Http\Controllers\Admin\PartnerSettingController;
use App\Http\Controllers\Admin\ProductOnboardingController as AdminProductOnboardingController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SocialRegistrationController;
use App\Http\Controllers\AvatarImageController;
use App\Http\Controllers\BlockMemberController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationIndexController;
use App\Http\Controllers\ConversationReadController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\EventCancellationController;
use App\Http\Controllers\EventChatController;
use App\Http\Controllers\EventChatMessageController;
use App\Http\Controllers\EventChatReadController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventParticipantController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventRegistrationDecisionController;
use App\Http\Controllers\EventRegistrationIndexController;
use App\Http\Controllers\EventRegistrationRemovalController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LikeMemberController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MyEventController;
use App\Http\Controllers\NotificationIndexController;
use App\Http\Controllers\NotificationReadAllController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\Partner\AnnouncementController as PartnerAnnouncementController;
use App\Http\Controllers\Partner\AnnouncementRevisionController;
use App\Http\Controllers\Partner\AnnouncementSubmissionController;
use App\Http\Controllers\Partner\ProfileController as PartnerProfileController;
use App\Http\Controllers\Partner\ProfileImageController as PartnerProfileImageController;
use App\Http\Controllers\Partner\ProfileSubmissionController as PartnerProfileSubmissionController;
use App\Http\Controllers\PresenceHeartbeatController;
use App\Http\Controllers\ProductOnboardingController;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\PublicMatchingController;
use App\Http\Controllers\PublicMemberProfileController;
use App\Http\Controllers\SwipeController;
use App\Http\Controllers\UnblockMemberController;
use App\Support\PublicUrls;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicLandingController::class, 'redirect'])->name('home');
Route::get('partner-profiles/{partnerProfile}/image', [PublicLandingController::class, 'image'])
    ->name('partner-profiles.image');
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

Route::middleware(['auth', 'verified', 'social'])->group(function (): void {
    Route::get('avatars/{avatar}/image', AvatarImageController::class)
        ->name('avatars.image');

    Route::get('partner/profile-revisions/{revision}/image', PartnerProfileImageController::class)
        ->name('partner.profile-revisions.image');

    Route::prefix('partner')->name('partner.')->middleware('role:partner')->group(function (): void {
        Route::get('profile', [PartnerProfileController::class, 'edit'])
            ->name('profile.edit');
        Route::put('profile', [PartnerProfileController::class, 'update'])
            ->name('profile.update');
        Route::post('profile/submit', PartnerProfileSubmissionController::class)
            ->name('profile.submit');
        Route::resource('announcements', PartnerAnnouncementController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('announcements/{announcement}/submit', AnnouncementSubmissionController::class)
            ->name('announcements.submit');
        Route::post('announcements/{announcement}/cancel', [PartnerAnnouncementController::class, 'cancel'])
            ->name('announcements.cancel');
        Route::post('announcements/{announcement}/revise', AnnouncementRevisionController::class)
            ->name('announcements.revise');
    });

    Route::middleware('role:user')->group(function (): void {
        Route::get('app', LandingController::class)->name('app');

        Route::post('presence/heartbeat', PresenceHeartbeatController::class)
            ->middleware('throttle:30,1')
            ->name('presence.heartbeat');

        Route::get('profile/create', [MemberProfileController::class, 'create'])
            ->name('member-profile.create');
        Route::post('profile', [MemberProfileController::class, 'store'])
            ->name('member-profile.store');

        Route::middleware('profile.complete')->group(function (): void {
            Route::get('onboarding', [ProductOnboardingController::class, 'show'])
                ->name('onboarding.show');
            Route::patch('onboarding', [ProductOnboardingController::class, 'advance'])
                ->name('onboarding.advance');
            Route::post('onboarding/complete', [ProductOnboardingController::class, 'complete'])
                ->name('onboarding.complete');

            Route::middleware('onboarding.complete')->group(function (): void {
                Route::get('profile', [MemberProfileController::class, 'show'])
                    ->name('member-profile.show');
                Route::get('profile/edit', [MemberProfileController::class, 'edit'])
                    ->name('member-profile.edit');
                Route::patch('profile', [MemberProfileController::class, 'update'])
                    ->name('member-profile.update');

                Route::get('members/{member}', PublicMemberProfileController::class)
                    ->name('members.show');
                Route::post('members/{member}/like', LikeMemberController::class)
                    ->name('members.like');
                Route::post('members/{member}/block', BlockMemberController::class)
                    ->name('members.block');
                Route::delete('members/{member}/block', UnblockMemberController::class)
                    ->name('members.unblock');

                Route::get('discover', DiscoveryController::class)
                    ->name('discovery.index');
                Route::post('discover/{target}/swipe', SwipeController::class)
                    ->name('discovery.swipe');

                Route::get('events/mine', MyEventController::class)->name('events.mine');
                Route::resource('events', EventController::class)
                    ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
                Route::get('events/{event}/participants', [EventParticipantController::class, 'index'])
                    ->name('events.participants.index');
                Route::get('events/{event}/participants/{member}', [EventParticipantController::class, 'show'])
                    ->name('events.participants.show');
                Route::get('events/{event}/requests', EventRegistrationIndexController::class)
                    ->name('events.registrations.index');
                Route::patch('events/{event}/cancel', EventCancellationController::class)
                    ->name('events.cancel');
                Route::post('events/{event}/registrations', [EventRegistrationController::class, 'store'])
                    ->name('events.registrations.store');
                Route::delete('events/{event}/registrations', [EventRegistrationController::class, 'destroy'])
                    ->name('events.registrations.destroy');
                Route::post('events/{event}/chat/messages', EventChatMessageController::class)
                    ->name('events.chat.messages.store');
                Route::get('events/{event}/chat', EventChatController::class)
                    ->name('events.chat.show');
                Route::post('events/{event}/chat/read', EventChatReadController::class)
                    ->name('events.chat.read.store');
                Route::patch('event-registrations/{registration}', EventRegistrationDecisionController::class)
                    ->name('events.registrations.decision');
                Route::delete('event-registrations/{registration}', EventRegistrationRemovalController::class)
                    ->name('events.registrations.remove');

                Route::get('conversations', ConversationIndexController::class)
                    ->name('conversations.index');
                Route::get('conversations/{conversation}', ConversationController::class)
                    ->name('conversations.show');
                Route::post('conversations/{conversation}/messages', MessageController::class)
                    ->name('conversations.messages.store');
                Route::post('conversations/{conversation}/read', ConversationReadController::class)
                    ->name('conversations.read.store');
                Route::get('notifications', NotificationIndexController::class)
                    ->name('notifications.index');
                Route::patch('notifications/read-all', NotificationReadAllController::class)
                    ->name('notifications.read-all');
                Route::patch('notifications/{notification}/read', NotificationReadController::class)
                    ->name('notifications.read');
            });
        });
    });

    Route::get('dashboard', DashboardController::class)
        ->middleware('role:admin')
        ->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('partner-announcements', [AdminPartnerAnnouncementController::class, 'index'])
            ->name('partner-announcements.index');
        Route::patch('partner-announcements/{announcement}', PartnerAnnouncementDecisionController::class)
            ->name('partner-announcements.decide');
        Route::post('partner-announcements/{announcement}/dispatch', PartnerAnnouncementDispatchController::class)
            ->name('partner-announcements.dispatch');
        Route::post('partner-announcements/{announcement}/retry', PartnerAnnouncementRetryController::class)
            ->name('partner-announcements.retry');
        Route::patch('partner-settings', PartnerSettingController::class)
            ->name('partner-settings.update');
        Route::get('partner-profiles', [AdminPartnerProfileController::class, 'index'])
            ->name('partner-profiles.index');
        Route::patch('partner-profile-revisions/{revision}', PartnerProfileDecisionController::class)
            ->name('partner-profile-revisions.decide');
        Route::delete('partner-profiles/{partnerProfile}/publication', [AdminPartnerProfileController::class, 'destroy'])
            ->name('partner-profiles.unpublish');
        Route::patch('partner-profiles/order', PartnerProfileOrderController::class)
            ->name('partner-profiles.order');
        Route::resource('avatars', AvatarController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::patch('avatars/{avatar}/status', AvatarStatusController::class)
            ->name('avatars.status');
        Route::patch('avatars/{avatar}/move', AvatarOrderController::class)
            ->name('avatars.move');
        Route::get('onboarding', [AdminProductOnboardingController::class, 'index'])
            ->name('onboarding.index');
        Route::patch('onboarding', [AdminProductOnboardingController::class, 'update'])
            ->name('onboarding.update');
        Route::get('members', [AdminMemberController::class, 'index'])
            ->name('members.index');
        Route::patch('members/{member}/roles', MemberRoleController::class)
            ->name('members.roles.update');
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

require __DIR__.'/settings.php';
