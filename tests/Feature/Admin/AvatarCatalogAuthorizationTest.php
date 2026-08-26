<?php

namespace Tests\Feature\Admin;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarCatalogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_the_avatar_catalog(): void
    {
        expect(Route::has('admin.avatars.index'))->toBeTrue();

        Storage::fake('local');
        $avatar = Avatar::query()->create([
            'name' => 'Aurore',
            'image_path' => 'avatars/aurore.png',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.avatars.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.avatars.store'), [
            'name' => 'Nouveau',
            'image' => UploadedFile::fake()->image('avatar.png'),
            'primary_color' => '#111111',
            'secondary_color' => '#222222',
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.avatars.update', $avatar), [
            'name' => 'Modifié',
            'primary_color' => '#111111',
            'secondary_color' => '#222222',
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.avatars.status', $avatar), [
            'is_active' => false,
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.avatars.move', $avatar), [
            'direction' => 'up',
        ])->assertForbidden();
        $this->actingAs($member)->delete(route('admin.avatars.destroy', $avatar))
            ->assertForbidden();
    }
}
