<?php

use App\Actions\RegisterForEvent;
use App\Actions\SetMemberBan;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an in flight registration cannot join after the member is banned', function () {
    $member = User::factory()->withProfile()->create();
    $event = Event::factory()->create();
    app(SetMemberBan::class)->handle(User::factory()->admin()->create(), $member, true, 'Audit regression');

    expect(fn () => app(RegisterForEvent::class)->handle($member, $event))->toThrow(AuthorizationException::class);
    $this->assertDatabaseEmpty('event_registrations');
    $this->assertDatabaseEmpty('group_chat_messages');
});

test('an in flight registration rechecks member eligibility', function (string $change) {
    $member = User::factory()->withProfile()->create();
    $event = Event::factory()->create();
    $member->load('roles', 'profile');
    match ($change) {
        'role' => $member->roles()->detach(),
        'email' => User::query()->whereKey($member->id)->update(['email_verified_at' => null]),
        'age' => User::query()->whereKey($member->id)->update(['birth_date' => today()->subYears(17)]),
        'profile' => $member->profile()->delete(),
    };

    expect(fn () => app(RegisterForEvent::class)->handle($member, $event))->toThrow(AuthorizationException::class);
    $this->assertDatabaseEmpty('event_registrations');
})->with(['role', 'email', 'age', 'profile']);
