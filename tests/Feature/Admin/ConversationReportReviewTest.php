<?php

use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets admins read all original messages after closure without marking them read', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $reporter = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $admin = User::factory()->withProfile()->admin()->create();
    $conversation = Conversation::query()->create(['match_id' => MemberMatch::query()->create(['user_low_id' => $reporter->id, 'user_high_id' => $target->id])->id]);
    $message = $conversation->messages()->create(['author_user_id' => $target->id, 'content' => 'Old message']);
    $this->actingAs($reporter)->post("/conversations/{$conversation->id}/reports", ['reason' => 'other', 'block' => false, 'confirmed' => true])->assertRedirect();
    $reportId = DB::table('conversation_reports')->value('id');
    $endpoint = "/admin/conversation-reports/{$reportId}";
    $this->get($endpoint)->assertForbidden();
    $this->actingAs($admin)->get($endpoint)->assertInertia(fn (Assert $page) => $page->component('Admin/ConversationReports/Show', false)->where('messages.data.0.content', 'Old message'));
    expect($message->fresh()->read_at)->toBeNull();
    $this->patch($endpoint, ['decision' => 'Reviewed', 'confirmed' => true])->assertRedirect();
    $conversation->messages()->create(['author_user_id' => $target->id, 'content' => 'Later message']);
    $conversation->update(['archived_at' => now()]);
    $this->get($endpoint)->assertInertia(fn (Assert $page) => $page->has('messages.data', 2));
    $this->assertDatabaseCount('moderation_audits', 3);
    $this->post("/conversations/{$conversation->id}/messages", ['content' => 'Forbidden'])->assertForbidden();
    $this->get('/admin/conversation-reports/999999')->assertNotFound();
    $this->actingAs($reporter)->post("/conversations/{$conversation->id}/reports", ['reason' => 'other', 'block' => false, 'confirmed' => true])->assertRedirect();
    $this->assertDatabaseCount('conversation_reports', 2);
});

it('retains review access after blocking hiding and banning while forbidding participant mutations', function () {
    $reporter = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $admin = User::factory()->withProfile()->admin()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $reporter->id, 'user_high_id' => $target->id])->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $target->id, 'content' => 'Evidence']);
    $this->actingAs($reporter)->post("/conversations/{$conversation->id}/reports", ['reason' => 'threats', 'block' => true, 'confirmed' => true])->assertRedirect();
    $report = ConversationReport::query()->firstOrFail();
    $reporter->profile->update(['visibility' => 'hidden']);
    $target->forceFill(['status' => 'banned'])->save();
    $endpoint = "/admin/conversation-reports/{$report->id}";
    $this->actingAs($admin)->get($endpoint)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.content', 'Evidence'));
    $this->post("/conversations/{$conversation->id}/read")->assertForbidden();
    $this->post("/conversations/{$conversation->id}/messages/{$message->id}/like")->assertForbidden();
    expect($message->fresh()->read_at)->toBeNull();
    $this->assertDatabaseCount('message_reactions', 0);
    $this->patch($endpoint, ['decision' => str_repeat('x', 1001), 'confirmed' => true])->assertSessionHasErrors('decision');
    $this->patch($endpoint, ['decision' => 'Closed', 'confirmed' => false])->assertSessionHasErrors('confirmed');
    expect($report->fresh()->closed_at)->toBeNull();
    $admin->forceFill(['status' => 'pending_deletion'])->save();
    $this->get($endpoint)->assertForbidden();
});

it('paginates all original messages in stable chronological order', function () {
    $reporter = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();
    $admin = User::factory()->withProfile()->admin()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $reporter->id, 'user_high_id' => $target->id])->conversation()->create();
    foreach (range(1, 51) as $number) {
        $conversation->messages()->create(['author_user_id' => $target->id, 'content' => "Original {$number}", 'created_at' => now()->subYear()]);
    }
    $this->actingAs($reporter)->post("/conversations/{$conversation->id}/reports", ['reason' => 'other', 'block' => false, 'confirmed' => true]);
    $id = ConversationReport::query()->value('id');
    $this->actingAs($admin)->get("/admin/conversation-reports/{$id}")->assertInertia(fn (Assert $page) => $page->has('messages.data', 50)->where('messages.data.0.content', 'Original 1')->where('messages.data.49.content', 'Original 50'));
    $this->get("/admin/conversation-reports/{$id}?page=2")->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.content', 'Original 51'));
});
