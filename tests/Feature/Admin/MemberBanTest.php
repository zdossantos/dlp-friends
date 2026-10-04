<?php

use App\Actions\RequestAccountDeletion;
use App\Enums\EventRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('bans permanently, retains data and revokes sessions, then allows an audited lifting', function () {
    Queue::fake();
    $admin = User::factory()->withProfile()->admin()->create();
    $member = User::factory()->withProfile()->create();
    DB::table('sessions')->insert(['id' => 'existing', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    $endpoint = "/admin/members/{$member->id}/ban";
    $this->actingAs($member)->patch($endpoint, ['banned' => true, 'reason' => 'Threats', 'confirmed' => true])->assertForbidden();
    $this->actingAs($admin)->patch($endpoint, ['banned' => true, 'reason' => 'Threats', 'confirmed' => false])->assertSessionHasErrors('confirmed');
    $this->patch($endpoint, ['banned' => true, 'reason' => 'Threats', 'confirmed' => true])->assertRedirect();
    expect($member->fresh()->status->value)->toBe('banned');
    expect($member->fresh()->profile)->not->toBeNull();
    $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
    $this->assertDatabaseHas('moderation_audits', ['target_user_id' => $member->id, 'operation' => 'ban', 'reason' => 'Threats']);
    $this->actingAs($member)->get('/conversations')->assertForbidden();
    expect(fn () => app(RequestAccountDeletion::class)->handle($member))->toThrow(HttpException::class);
    $this->actingAs($admin)->patch($endpoint, ['banned' => false, 'reason' => 'Appeal approved', 'confirmed' => true])->assertRedirect();
    expect($member->fresh()->status)->toBe(UserStatus::Active);
    $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
});

it('never bans an administrator', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $other = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin)->patch("/admin/members/{$other->id}/ban", ['banned' => true, 'reason' => 'Not allowed', 'confirmed' => true])->assertForbidden();
    expect($other->fresh()->status)->toBe(UserStatus::Active);
});

it('cancels future activity and neutralizes a pending deletion without removing identities', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->partner()->create(['status' => 'pending_deletion', 'deletion_requested_at' => now()->subDays(40)]);
    $member->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'kept']);
    $organized = Event::factory()->create(['organizer_user_id' => $member->id]);
    $event = Event::factory()->create();
    $registration = $event->registrations()->create(['user_id' => $member->id, 'status' => 'accepted']);
    $this->actingAs($admin)->patch("/admin/members/{$member->id}/ban", ['banned' => true, 'reason' => 'Abuse', 'confirmed' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect($organized->fresh()->cancelled_at)->not->toBeNull();
    expect($registration->fresh()->status)->toBe(EventRegistrationStatus::Withdrawn);
    expect($member->fresh()->deletion_requested_at)->toBeNull();
    $this->assertDatabaseHas('social_accounts', ['provider_user_id' => 'kept']);
    $this->patch("/admin/members/{$member->id}/ban", ['banned' => false, 'reason' => 'Reviewed', 'confirmed' => true])->assertRedirect();
    expect($organized->fresh()->cancelled_at)->not->toBeNull();
    expect($registration->fresh()->status)->toBe(EventRegistrationStatus::Withdrawn);
});
