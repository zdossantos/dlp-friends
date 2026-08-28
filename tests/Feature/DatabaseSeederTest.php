<?php

use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

test('it seeds 24 reusable completed demo members idempotently', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $demoMembers = User::query()
        ->with(['profile.avatar', 'profile.interests'])
        ->where('email', 'like', 'user%@example.com')
        ->orderBy('email')
        ->get();

    expect($demoMembers)->toHaveCount(24)
        ->and($demoMembers->pluck('email')->all())->toContain(
            'user1@example.com',
            'user24@example.com',
        );

    foreach ($demoMembers as $member) {
        expect($member->profile?->isComplete())->toBeTrue()
            ->and($member->profile?->interests)->not->toBeEmpty()
            ->and($member->profile?->interests)->toHaveCount(5)
            ->and(Hash::check('password', $member->password))->toBeTrue();
    }

    $this->assertDatabaseCount('users', 25);

    $setting = ProductOnboardingSetting::current();

    expect($setting)->not->toBeNull()
        ->and($setting?->pass_avatar_id)->not->toBe($setting?->like_avatar_id)
        ->and($setting?->passAvatar?->is_active)->toBeTrue()
        ->and($setting?->likeAvatar?->is_active)->toBeTrue();
});
