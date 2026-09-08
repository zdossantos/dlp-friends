<?php

namespace Tests\Feature\Settings;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_deletion_is_persisted_and_blocks_social_access(): void
    {
        $user = User::factory()->withProfile()->create([
            'status' => UserStatus::PendingDeletion,
            'deletion_requested_at' => now(),
        ]);

        $this->actingAs($user)->get(route('discovery.index'))->assertForbidden();

        $this->assertInstanceOf(CarbonImmutable::class, $user->fresh()->deletion_requested_at);
    }
}
