<?php

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reporter = User::factory()->withProfile()->create();
    $this->target = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create(['user_low_id' => $this->reporter->id, 'user_high_id' => $this->target->id]);
    $this->conversation = Conversation::query()->create(['match_id' => $match->id]);
    $this->endpoint = "/conversations/{$this->conversation->id}/reports";
});

it('reports without blocking and prevents duplicate open reports', function () {
    $payload = ['reason' => 'harassment', 'details' => 'Repeated contact', 'block' => false, 'confirmed' => true];
    $this->actingAs($this->reporter)->post($this->endpoint, $payload)->assertRedirect();
    $this->assertDatabaseHas('conversation_reports', ['reporter_user_id' => $this->reporter->id, 'target_user_id' => $this->target->id, 'reason' => 'harassment', 'closed_at' => null]);
    $this->assertDatabaseCount('blocks', 0);
    $this->post($this->endpoint, $payload)->assertSessionHasErrors('conversation');
    $this->assertDatabaseCount('conversation_reports', 1);
});

it('atomically blocks when chosen and allows reporting an archived exchange', function () {
    $this->conversation->update(['archived_at' => now()]);
    $this->actingAs($this->reporter)->post($this->endpoint, ['reason' => 'threats', 'block' => true, 'confirmed' => true])->assertRedirect();
    $this->assertDatabaseHas('blocks', ['blocker_user_id' => $this->reporter->id, 'blocked_user_id' => $this->target->id]);
    $this->assertDatabaseCount('conversation_reports', 1);
});

it('requires a valid reason, bounded detail and explicit confirmation', function (array $changes, string $field) {
    $this->actingAs($this->reporter)->post($this->endpoint, array_replace(['reason' => 'other', 'block' => false, 'confirmed' => true], $changes))->assertSessionHasErrors($field);
})->with([
    [['reason' => 'invalid'], 'reason'],
    [['details' => str_repeat('a', 1001)], 'details'],
    [['confirmed' => false], 'confirmed'],
]);

it('forbids an unrelated member and cannot block an administrator', function () {
    $outsider = User::factory()->withProfile()->create();
    $payload = ['reason' => 'other', 'block' => false, 'confirmed' => true];
    $this->actingAs($outsider)->post($this->endpoint, $payload)->assertForbidden();
    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
    $this->target->roles()->attach($adminRole);
    $this->actingAs($this->reporter)->post($this->endpoint, array_replace($payload, ['block' => true]))->assertSessionHasErrors('block');
    $this->assertDatabaseCount('conversation_reports', 0);
    $this->post($this->endpoint, $payload)->assertRedirect();
    $this->assertDatabaseCount('conversation_reports', 1);
});
