<?php

use App\Actions\BuildUserDataExport;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\MemberFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    Queue::fake();
});

function memberFeedbackInput(array $overrides = []): array
{
    return array_replace(['kind' => 'idea', 'area' => 'discovery', 'content' => 'Permettre de mieux comprendre les suggestions.'], $overrides);
}

test('members submit private feedback with server owned author and initial status without communications', function () {
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $this->actingAs($member)->from('/profile')->post('/feedback', memberFeedbackInput([
        'user_id' => $other->id, 'status' => 'done', 'internal_note' => 'injected',
    ]))->assertRedirect('/profile');
    $feedback = MemberFeedback::query()->sole();
    expect($feedback->user_id)->toBe($member->id)
        ->and($feedback->status->value)->toBe('pending')
        ->and($feedback->internal_note)->toBeNull();
    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    $this->assertDatabaseCount('notifications', 0);
});

test('feedback validates catalogues and nonblank bounded text', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->withProfile()->create())->post('/feedback', memberFeedbackInput($overrides))
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('member_feedback', 0);
})->with([
    [['kind' => 'feature'], 'kind'], [['kind' => ''], 'kind'], [['kind' => ['idea']], 'kind'],
    [['area' => 'secret'], 'area'], [['area' => ''], 'area'],
    [['content' => ''], 'content'], [['content' => " \n\t"], 'content'],
    [['content' => str_repeat('a', 5001)], 'content'], [['content' => ['text']], 'content'],
]);

test('feedback accepts every advertised kind and area', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    foreach (['idea', 'problem', 'other'] as $kind) {
        $this->post('/feedback', memberFeedbackInput(['kind' => $kind, 'content' => str_repeat('é', 5000)]))->assertSessionHasNoErrors()->assertRedirect();
    }
    $this->travel(61)->minutes();
    foreach (['discovery', 'messaging', 'events', 'visits', 'trips', 'profile', 'settings', 'application', 'other'] as $index => $area) {
        if ($index === 5) {
            $this->travel(61)->minutes();
        }
        $this->post('/feedback', memberFeedbackInput(['area' => $area]))->assertSessionHasNoErrors()->assertRedirect();
    }
    $this->assertDatabaseCount('member_feedback', 12);
});

test('feedback requires an eligible member and limits attempts per account', function () {
    $this->post('/feedback', memberFeedbackInput())->assertRedirect('/login');
    $this->actingAs(User::factory()->partnerOnly()->create())->post('/feedback', memberFeedbackInput())->assertForbidden();
    $this->actingAs(User::factory()->unverified()->withProfile()->create())->post('/feedback', memberFeedbackInput())->assertRedirect('/email/verify');
    $this->actingAs(User::factory()->withProfile()->create());
    for ($i = 0; $i < 5; $i++) {
        $this->post('/feedback', memberFeedbackInput())->assertRedirect();
    }
    $this->post('/feedback', memberFeedbackInput())->assertTooManyRequests();
    $this->actingAs(User::factory()->withProfile()->create())->post('/feedback', memberFeedbackInput())->assertRedirect();
});

test('members cannot read or update any feedback even their own', function () {
    $member = User::factory()->withProfile()->create();
    $feedback = MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput(), 'internal_note' => 'Private note']);
    foreach ([$member, User::factory()->withProfile()->create()] as $actor) {
        $this->actingAs($actor)->get('/admin/feedback')->assertForbidden();
        $this->get("/admin/feedback/{$feedback->id}")->assertForbidden();
        $this->patch("/admin/feedback/{$feedback->id}", ['status' => 'done', 'internal_note' => 'changed'])->assertForbidden();
        $this->get("/feedback/{$feedback->id}")->assertNotFound();
    }
    expect($feedback->fresh()->internal_note)->toBe('Private note');
});

test('admins filter paginate read and process feedback without communicating', function () {
    $member = User::factory()->withProfile()->create();
    $feedback = MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput()]);
    MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput(['kind' => 'problem', 'area' => 'messaging']), 'status' => 'done']);
    $this->actingAs(User::factory()->admin()->create())->get('/admin/feedback?kind=idea&area=discovery&status=pending')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Feedback/Index')->has('feedback.data', 1)->where('feedback.data.0.id', $feedback->id)->missing('feedback.data.0.internal_note')->missing('feedback.data.0.content'));
    $this->get("/admin/feedback/{$feedback->id}")->assertInertia(fn (Assert $page) => $page->component('Admin/Feedback/Show')->where('feedback.author.id', $member->id)->where('feedback.content', $feedback->content)->missing('feedback.author.email'));
    foreach (['studying', 'accepted', 'declined', 'done', 'pending'] as $status) {
        $this->patch("/admin/feedback/{$feedback->id}", ['status' => $status, 'internal_note' => 'Note confidentielle'])->assertRedirect()->assertSessionHasNoErrors();
        expect($feedback->fresh()->status->value)->toBe($status)->and($feedback->fresh()->internal_note)->toBe('Note confidentielle');
    }
    $this->patch("/admin/feedback/{$feedback->id}", ['status' => 'pending', 'internal_note' => null])->assertRedirect();
    expect($feedback->fresh()->internal_note)->toBeNull();
    $this->get('/admin/feedback?kind=invalid')->assertSessionHasErrors('kind');
    $this->patch("/admin/feedback/{$feedback->id}", ['status' => 'invalid'])->assertSessionHasErrors('status');
    $this->patch("/admin/feedback/{$feedback->id}", ['status' => 'done', 'internal_note' => str_repeat('a', 5001)])->assertSessionHasErrors('internal_note');
    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    $this->assertDatabaseCount('notifications', 0);
});

test('feedback export includes only authored submissions without internal tracking', function () {
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput(), 'status' => 'accepted', 'internal_note' => 'Private']);
    MemberFeedback::query()->create(['user_id' => $other->id, ...memberFeedbackInput(['content' => 'Someone else'])]);
    $export = app(BuildUserDataExport::class)->handle($member)['feedback'];
    expect($export)->toHaveCount(1)->and(array_keys($export[0]))->toBe(['kind', 'area', 'content', 'created_at'])
        ->and($export[0]['content'])->toBe(memberFeedbackInput()['content']);
});

test('feedback expires at two years except banned accounts and cascades with account purge', function () {
    $this->freezeTime();
    $member = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => UserStatus::Banned]);
    $expired = MemberFeedback::query()->forceCreate(['user_id' => $member->id, ...memberFeedbackInput(), 'created_at' => now()->subYears(2), 'internal_note' => 'Old note']);
    $recent = MemberFeedback::query()->forceCreate(['user_id' => $member->id, ...memberFeedbackInput(), 'created_at' => now()->subYears(2)->addSecond()]);
    $protected = MemberFeedback::query()->forceCreate(['user_id' => $banned->id, ...memberFeedbackInput(), 'created_at' => now()->subYears(3)]);
    $this->artisan('feedback:purge-expired')->assertSuccessful();
    expect($expired->fresh())->toBeNull()->and($recent->fresh())->not->toBeNull()->and($protected->fresh())->not->toBeNull();
    $member->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays(30)])->save();
    (new PurgeDeletedUser($member->id, $member->deletion_requested_at->toISOString()))->handle();
    expect($recent->fresh())->toBeNull();
    $banned->delete();
    expect($protected->fresh())->toBeNull();
});

test('admin pagination preserves filters and excludes unrelated returns', function () {
    $member = User::factory()->withProfile()->create();
    for ($i = 0; $i < 22; $i++) {
        MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput()]);
    }
    MemberFeedback::query()->create(['user_id' => $member->id, ...memberFeedbackInput(['kind' => 'problem'])]);
    $this->actingAs(User::factory()->admin()->create())->get('/admin/feedback?kind=idea')
        ->assertInertia(fn (Assert $page) => $page->has('feedback.data', 20)->where('feedback.total', 22)->where('feedback.next_page_url', fn ($url) => str_contains($url, 'kind=idea') && str_contains($url, 'page=2')));
    $this->get('/admin/feedback?kind=idea&page=2')->assertInertia(fn (Assert $page) => $page->has('feedback.data', 2));
});

test('profile advertises only submission options and invalid attempts also count towards throttling', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->get('/profile')->assertInertia(fn (Assert $page) => $page->has('feedbackOptions.kinds', 3)->has('feedbackOptions.areas', 9)->missing('feedbackOptions.statuses')->missing('feedback'));
    for ($i = 0; $i < 5; $i++) {
        $this->post('/feedback', memberFeedbackInput(['content' => '']))->assertSessionHasErrors('content');
    }
    $this->post('/feedback', memberFeedbackInput())->assertTooManyRequests();
    $this->assertDatabaseCount('member_feedback', 0);
});

test('banned members and administrators cannot access feedback', function () {
    $member = User::factory()->withProfile()->create(['status' => UserStatus::Banned]);
    $this->actingAs($member)->post('/feedback', memberFeedbackInput())->assertForbidden();
    $admin = User::factory()->admin()->create(['status' => UserStatus::Banned]);
    $this->actingAs($admin)->get('/admin/feedback')->assertForbidden();
});

test('presence heartbeats do not consume the hourly feedback quota', function () {
    Event::fake();
    $this->actingAs(User::factory()->withProfile()->create());
    for ($i = 0; $i < 5; $i++) {
        $this->post('/presence/heartbeat')->assertNoContent();
    }
    for ($i = 0; $i < 5; $i++) {
        $this->post('/feedback', memberFeedbackInput())->assertRedirect();
    }
    $this->post('/feedback', memberFeedbackInput())->assertTooManyRequests();
    $this->post('/presence/heartbeat')->assertNoContent();
});
