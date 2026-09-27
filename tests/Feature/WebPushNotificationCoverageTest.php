<?php

use App\Contracts\WebPushNotification;
use App\Enums\WebPushPreference;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\EventLifecycleNotification;
use App\Notifications\MessageLikedNotification;
use App\Notifications\NewMatchNotification;
use App\Notifications\NewMessageNotification;
use App\Notifications\PartnerAnnouncementDecisionNotification;
use App\Notifications\PartnerAnnouncementNotification;
use App\Notifications\PartnerModerationRequestedNotification;
use App\Support\WebPushTarget;

test('every internal notification category uses the private web push channel', function (string $class, WebPushPreference $preference) {
    $notification = (new ReflectionClass($class))->newInstanceWithoutConstructor();

    expect($notification)->toBeInstanceOf(WebPushNotification::class)
        ->and($notification->webPushPreference())->toBe($preference)
        ->and($notification->via(new User))->toContain(WebPushChannel::class);
})->with([
    [NewMessageNotification::class, WebPushPreference::Messages],
    [MessageLikedNotification::class, WebPushPreference::Messages],
    [NewMatchNotification::class, WebPushPreference::Matches],
    [EventLifecycleNotification::class, WebPushPreference::Events],
    [PartnerAnnouncementNotification::class, WebPushPreference::PartnerAnnouncements],
    [PartnerAnnouncementDecisionNotification::class, WebPushPreference::PartnerAnnouncements],
    [PartnerModerationRequestedNotification::class, WebPushPreference::Administration],
]);

test('push targets accept only same-origin allowlisted paths', function () {
    expect(WebPushTarget::isAllowed('/conversations/42'))->toBeTrue()
        ->and(WebPushTarget::isAllowed('/events/8?tab=chat'))->toBeTrue()
        ->and(WebPushTarget::isAllowed('https://evil.example/conversations/42'))->toBeFalse()
        ->and(WebPushTarget::isAllowed('//evil.example/notifications'))->toBeFalse()
        ->and(WebPushTarget::isAllowed('/settings/security'))->toBeFalse();
});

test('partner moderation pushes open the matching admin review page', function (string $targetType, string $expectedUrl) {
    $notification = new PartnerModerationRequestedNotification(
        'notifications.items.partner_profile_review_requested',
        [],
        $targetType,
        42,
    );

    expect($notification->webPushTarget(new User)->url)->toBe($expectedUrl);
})->with([
    'partner profile' => ['admin_partner_profile_review', '/admin/partner-profiles'],
    'partner announcement' => ['admin_partner_announcement_review', '/admin/partner-announcements'],
]);

test('system push copy never exposes user or content placeholders', function (string $locale) {
    foreach (WebPushPreference::cases() as $preference) {
        $copy = trans('notifications.push.'.$preference->value, locale: $locale);
        expect($copy)->not->toContain(':')->not->toContain('{');
    }
})->with(['fr', 'en']);
