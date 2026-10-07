<?php

use App\Actions\BuildUserDataExport;
use App\Enums\WebPushPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('weekly email preferences default to enabled without affecting web push', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->get(route('notification-preferences.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('emailPreferences.weekly_recap_messages', true)
        ->where('emailPreferences.weekly_recap_matches', true)
        ->where('preferences.messages', true));
    expect($member->fresh()->weekly_recap_messages)->toBeTrue()
        ->and($member->fresh()->weekly_recap_matches)->toBeTrue();
});

test('email only updates persist independently and stay isolated from other accounts and push', function (string $field) {
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->create();
    foreach ([false, true] as $enabled) {
        $this->actingAs($member)->patch(route('notification-preferences.update'), [$field => $enabled])
            ->assertSessionHasNoErrors()->assertRedirect(route('notification-preferences.edit'));
        expect($member->fresh()->{$field})->toBe($enabled)
            ->and($other->fresh()->{$field})->toBeTrue()
            ->and($member->notificationPreferences()->where('category', WebPushPreference::Messages)->exists())->toBeFalse();
    }
    $unchanged = $field === 'weekly_recap_messages' ? 'weekly_recap_matches' : 'weekly_recap_messages';
    expect($member->fresh()->{$unchanged})->toBeTrue();
})->with(['weekly_recap_messages', 'weekly_recap_matches']);

test('weekly preferences reject invalid input', function (string $field) {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->patch(route('notification-preferences.update'), [$field => 'not-a-boolean'])
        ->assertSessionHasErrors($field);
    expect($member->fresh()->{$field})->toBeTrue();
})->with(['weekly_recap_messages', 'weekly_recap_matches']);

test('disable all also disables weekly email and its match counter', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->delete(route('notification-preferences.disable-all'))->assertRedirect();
    expect($member->fresh()->weekly_recap_messages)->toBeFalse()
        ->and($member->fresh()->weekly_recap_matches)->toBeFalse();
});

test('personal export includes effective weekly email preferences', function () {
    $member = User::factory()->withProfile()->create();
    $member->forceFill(['weekly_recap_messages' => false])->save();
    $export = app(BuildUserDataExport::class)->handle($member);
    expect($export['email_preferences'])->toBe(['weekly_recap_messages' => false, 'weekly_recap_matches' => true]);
});
