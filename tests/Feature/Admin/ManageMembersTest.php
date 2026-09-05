<?php

namespace Tests\Feature\Admin;

use App\Enums\SwipeDecision;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\Swipe;
use App\Models\User;
use App\Mail\MemberDeletedByAdminMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_only_administrators_can_view_the_member_catalog(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('admin.members.index'))
            ->assertForbidden();
    }

    public function test_catalog_searches_members_and_exposes_directional_statistics_without_message_content(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $member = User::factory()->withProfile()->create(['email' => 'target@example.com']);
        $member->profile?->update(['display_name' => 'Belle Cible']);
        $other = User::factory()->withProfile()->create();
        $third = User::factory()->withProfile()->create();

        Swipe::factory()->create(['actor_user_id' => $member->id, 'target_user_id' => $other->id, 'decision' => SwipeDecision::Like]);
        Swipe::factory()->create(['actor_user_id' => $other->id, 'target_user_id' => $member->id, 'decision' => SwipeDecision::Like]);
        Swipe::factory()->create(['actor_user_id' => $member->id, 'target_user_id' => $third->id, 'decision' => SwipeDecision::Pass]);
        Swipe::factory()->create(['actor_user_id' => $third->id, 'target_user_id' => $member->id, 'decision' => SwipeDecision::Pass]);

        $firstMatch = $this->createMatch($member, $other);
        $secondMatch = $this->createMatch($member, $third);
        $conversation = $firstMatch->conversation()->create();
        Message::factory()->count(2)->for($conversation)->create(['author_user_id' => $member->id]);
        Block::factory()->create(['blocker_user_id' => $member->id, 'blocked_user_id' => $other->id]);
        Block::factory()->create(['blocker_user_id' => $third->id, 'blocked_user_id' => $member->id]);

        $this->actingAs($admin)
            ->get(route('admin.members.index', ['search' => 'BELLE cible']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Members/Index')
                ->where('filters.search', 'BELLE cible')
                ->has('members.data', 1)
                ->where('members.per_page', 20)
                ->where('members.data.0.email', 'target@example.com')
                ->where('members.data.0.likes_sent_count', 1)
                ->where('members.data.0.likes_received_count', 1)
                ->where('members.data.0.passes_sent_count', 1)
                ->where('members.data.0.passes_received_count', 1)
                ->where('members.data.0.matches_count', 2)
                ->where('members.data.0.messages_sent_count', 2)
                ->where('members.data.0.blocked_count', 1)
                ->where('members.data.0.blocked_by_count', 1)
                ->missing('members.data.0.messages'));

        $this->actingAs($admin)
            ->get(route('admin.members.index', ['search' => 'TARGET@EXAMPLE.COM']))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1));
    }

    public function test_catalog_paginates_twenty_members_and_protects_admin_rows(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        User::factory()->withProfile()->count(19)->create();

        $this->actingAs($admin)
            ->get(route('admin.members.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 20)
                ->where('members.total', 20)
                ->where('members.data', fn (Collection $rows): bool => $rows
                    ->contains(fn (array $row): bool => $row['id'] === $admin->id
                        && $row['is_admin'] === true
                        && $row['can_delete'] === false
                        && $row['can_start_conversation'] === false)));
    }

    public function test_admin_deletes_an_ordinary_member_immediately_and_queues_a_localized_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->withProfile()->admin()->create();
        $member = User::factory()->withProfile()->create(['email' => 'deleted@example.com', 'locale' => 'en']);
        $displayName = $member->profile?->display_name;
        DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($admin)
            ->delete(route('admin.members.destroy', $member))
            ->assertRedirect(route('admin.members.index'));

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
        Mail::assertQueued(MemberDeletedByAdminMail::class, fn (MemberDeletedByAdminMail $mail): bool =>
            $mail->hasTo('deleted@example.com')
            && $mail->displayName === $displayName
            && $mail->locale === 'en');
    }

    public function test_admin_cannot_delete_another_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->withProfile()->admin()->create();
        $otherAdmin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.members.destroy', $otherAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
        Mail::assertNothingQueued();
    }

    public function test_mail_queue_failure_does_not_restore_the_deleted_member(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $member = User::factory()->withProfile()->create();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('mail unavailable'));

        $this->actingAs($admin)
            ->delete(route('admin.members.destroy', $member))
            ->assertRedirect(route('admin.members.index'));

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    private function createMatch(User $first, User $second): MemberMatch
    {
        [$lowId, $highId] = collect([$first->id, $second->id])->sort()->values()->all();

        return MemberMatch::factory()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ]);
    }
}
