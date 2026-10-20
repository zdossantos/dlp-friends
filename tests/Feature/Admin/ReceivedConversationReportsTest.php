<?php

use App\Models\ConversationReport;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function receivedReport(User $reporter, User $target, bool $closed = false): ConversationReport
{
    $conversation = MemberMatch::query()->create([
        'user_low_id' => min($reporter->id, $target->id),
        'user_high_id' => max($reporter->id, $target->id),
    ])->conversation()->create();

    return ConversationReport::query()->create([
        'conversation_id' => $conversation->id,
        'reporter_user_id' => $reporter->id,
        'target_user_id' => $target->id,
        'reason' => 'other',
        'closed_at' => $closed ? now() : null,
        'decision' => $closed ? 'Reviewed' : null,
    ]);
}

it('lists only reports received by the selected account including closed reports across every page', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $target = User::factory()->withProfile()->create();
    $reporter = User::factory()->withProfile()->create();
    $oldest = receivedReport($reporter, $target, true);
    foreach (range(1, 20) as $i) {
        receivedReport(User::factory()->withProfile()->create(), $target);
    }
    receivedReport($target, User::factory()->withProfile()->create());
    $url = "/admin/conversation-reports?target={$target->id}";
    $this->actingAs($admin)->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('target.id', $target->id)->where('status', 'all')
        ->where('reports.total', 21)->has('reports.data', 20)
        ->where('counts.open', 20)->where('counts.closed', 1));
    $this->get($url.'&page=2')->assertInertia(fn (Assert $page) => $page
        ->has('reports.data', 1)->where('reports.data.0.id', $oldest->id)
        ->where('reports.data.0.reporter.id', $reporter->id)
        ->where('reports.data.0.decision', 'Reviewed'));
    $this->get($url.'&closed=1')->assertInertia(fn (Assert $page) => $page
        ->where('status', 'closed')->has('reports.data', 1));
    $this->get($url.'&closed=0')->assertInertia(fn (Assert $page) => $page
        ->where('status', 'open')->where('reports.total', 20));
    $this->assertDatabaseCount('moderation_audits', 0);
});

it('offers an explicit empty history for accounts with no received reports', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $target = User::factory()->withProfile()->create();
    $this->actingAs($admin)->get("/admin/conversation-reports?target={$target->id}")
        ->assertInertia(fn (Assert $page) => $page->where('target.id', $target->id)->has('reports.data', 0));
    $this->get('/admin/conversation-reports?target=999999')->assertNotFound();
});

it('exposes the history entry on the normal profile only to authorized admins', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $target = User::factory()->withProfile()->create();
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($admin)->get("/members/{$target->id}")->assertInertia(fn (Assert $page) => $page
        ->where('receivedReportsHref', route('admin.conversation-reports.index', ['target' => $target->id, 'source' => 'profile'], absolute: false)));
    foreach ([$viewer, $target] as $user) {
        $this->actingAs($user)->get("/members/{$target->id}")->assertInertia(fn (Assert $page) => $page
            ->missing('receivedReportsHref')->missing('reports'));
        $this->get("/admin/conversation-reports?target={$target->id}")->assertForbidden();
    }
    $admin->forceFill(['status' => 'banned'])->save();
    $this->actingAs($admin)->get("/admin/conversation-reports?target={$target->id}")->assertForbidden();
});

it('preserves the filtered list context during message pagination and closure', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $target = User::factory()->withProfile()->create();
    $reporter = User::factory()->withProfile()->create();
    $report = receivedReport($reporter, $target);
    foreach (range(1, 51) as $i) {
        $report->conversation->messages()->create(['author_user_id' => $target->id, 'content' => "Evidence {$i}"]);
    }
    $context = "target={$target->id}&closed=0&list_page=2&source=profile";
    $this->actingAs($admin)->get("/admin/conversation-reports/{$report->id}?{$context}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('backHref', route('admin.conversation-reports.index', ['target' => $target->id, 'closed' => false, 'source' => 'profile', 'page' => 2], absolute: false))
            ->where('messages.next_page_url', fn ($url) => str_contains($url, 'list_page=2') && str_contains($url, "target={$target->id}")));
    $this->patch("/admin/conversation-reports/{$report->id}?{$context}", ['decision' => 'Reviewed', 'confirmed' => true])
        ->assertRedirect(route('admin.conversation-reports.show', ['report' => $report, 'target' => $target->id, 'closed' => false, 'source' => 'profile', 'list_page' => 2]));
    expect($report->fresh()->decision)->toBe('Reviewed');
    expect($report->conversation->messages()->whereNotNull('read_at')->exists())->toBeFalse();
    $this->assertDatabaseCount('moderation_audits', 2);
});
