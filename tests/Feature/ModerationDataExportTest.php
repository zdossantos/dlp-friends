<?php

use App\Actions\BuildUserDataExport;
use App\Actions\CloseConversationReport;
use App\Actions\ReportConversation;
use App\Actions\SetMemberBan;
use App\Enums\ConversationReportReason;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports only own reports and sanctions without other report details identities or messages', function () {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $admin = User::factory()->admin()->create(['email' => 'private-actor@example.test']);
    $conversation = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $peer->id])->conversation()->create();
    $conversation->messages()->create(['author_user_id' => $peer->id, 'content' => 'Private peer message']);
    $own = app(ReportConversation::class)->handle($member, $conversation, ConversationReportReason::Other, 'Own context', false);
    app(ReportConversation::class)->handle($peer, $conversation, ConversationReportReason::Other, 'Private peer context', false);
    app(CloseConversationReport::class)->handle($admin, $own, 'Own decision');
    app(SetMemberBan::class)->handle($admin, $member, true, 'Ban decision');
    app(SetMemberBan::class)->handle($admin, $member, false, 'Lift decision');
    $payload = app(BuildUserDataExport::class)->handle($member);
    expect($payload['moderation']['reports_submitted'])->toHaveCount(1)
        ->and($payload['moderation']['reports_submitted'][0]['details'])->toBe('Own context')
        ->and(array_column($payload['moderation']['decisions'], 'operation'))->toBe(['ban', 'unban']);
    $json = json_encode($payload);
    foreach (['Private peer message', 'Private peer context', 'private-actor@example.test', 'actor_user_id', 'reporter_user_id', 'target_user_id', 'decided_by'] as $private) {
        expect($json)->not->toContain($private);
    }
});
