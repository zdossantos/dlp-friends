<?php

namespace Tests\Unit\Models;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_excludes_archived_avatars(): void
    {
        expect(class_exists(Avatar::class))->toBeTrue();

        $active = Avatar::query()->create([
            'name' => 'Aurore',
            'image_path' => 'avatars/aurore.png',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Avatar::query()->create([
            'name' => 'Brume',
            'image_path' => 'avatars/brume.png',
            'primary_color' => '#0F172A',
            'secondary_color' => '#64748B',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        expect(Avatar::query()->active()->pluck('id')->all())->toBe([$active->id]);
    }

    public function test_profile_belongs_to_its_selected_avatar(): void
    {
        expect(class_exists(Avatar::class))->toBeTrue();

        $avatar = Avatar::query()->create([
            'name' => 'Aurore',
            'image_path' => 'avatars/aurore.png',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->update(['avatar_id' => $avatar->id]);

        expect($profile->fresh()->avatar->is($avatar))->toBeTrue();
    }
}
