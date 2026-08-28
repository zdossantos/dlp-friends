<?php

namespace Tests\Feature\Admin;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ManageAvatarCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_index_returns_the_ordered_avatar_catalog(): void
    {
        expect(Route::has('admin.avatars.index'))->toBeTrue();
        config()->set('inertia.testing.ensure_pages_exist', false);

        $later = $this->avatar(['name' => 'Brume', 'sort_order' => 20, 'is_active' => false]);
        $earlier = $this->avatar(['name' => 'Aurore', 'sort_order' => 10]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.avatars.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Avatars/Index')
                ->where('avatars', [
                    [
                        'id' => $earlier->id,
                        'name' => 'Aurore',
                        'image_url' => route('avatars.image', $earlier),
                        'primary_color' => '#7C3AED',
                        'secondary_color' => '#EC4899',
                        'is_active' => true,
                        'sort_order' => 10,
                        'profiles_count' => 0,
                        'used_by_onboarding' => false,
                    ],
                    [
                        'id' => $later->id,
                        'name' => 'Brume',
                        'image_url' => route('avatars.image', $later),
                        'primary_color' => '#7C3AED',
                        'secondary_color' => '#EC4899',
                        'is_active' => false,
                        'sort_order' => 20,
                        'profiles_count' => 0,
                        'used_by_onboarding' => false,
                    ],
                ]));
    }

    public function test_admin_can_create_and_update_an_avatar_with_an_image_and_two_colors(): void
    {
        expect(Route::has('admin.avatars.store'))->toBeTrue();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.avatars.store'), [
            'name' => '  Ami   étoilé  ',
            'image' => UploadedFile::fake()->image('avatar.png', 300, 300),
            'primary_color' => '#7c3aed',
            'secondary_color' => '#EC4899',
        ])->assertRedirect();

        $avatar = Avatar::query()->where('name', 'Ami étoilé')->firstOrFail();
        expect($avatar->primary_color)->toBe('#7C3AED')
            ->and($avatar->secondary_color)->toBe('#EC4899')
            ->and($avatar->is_active)->toBeTrue()
            ->and($avatar->sort_order)->toBe(0);
        Storage::disk('local')->assertExists($avatar->image_path);
        $originalPath = $avatar->image_path;

        $this->actingAs($admin)->patch(route('admin.avatars.update', $avatar), [
            'name' => 'Compagnon lumineux',
            'image' => UploadedFile::fake()->image('replacement.webp', 400, 400),
            'primary_color' => '#0F172A',
            'secondary_color' => '#38BDF8',
        ])->assertRedirect();

        $avatar->refresh();
        expect($avatar->name)->toBe('Compagnon lumineux')
            ->and($avatar->image_path)->not->toBe($originalPath);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($avatar->image_path);
    }

    public function test_uploaded_avatar_image_is_reencoded_without_embedded_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $source = UploadedFile::fake()->image('avatar.png', 100, 100);
        $marker = 'PRIVATE_METADATA_MARKER';
        $upload = UploadedFile::fake()->createWithContent(
            'avatar.png',
            file_get_contents($source->getPathname()).$marker,
        );

        $this->actingAs($admin)->post(route('admin.avatars.store'), [
            'name' => 'Sans métadonnées',
            'image' => $upload,
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $avatar = Avatar::query()->where('name', 'Sans métadonnées')->firstOrFail();

        expect(Storage::disk('local')->get($avatar->image_path))
            ->not->toContain($marker);
    }

    public function test_avatar_update_locks_the_current_row_before_replacing_its_image(): void
    {
        $avatar = $this->avatar();
        $admin = User::factory()->admin()->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->patch(route('admin.avatars.update', $avatar), [
            'name' => 'Aurore modifiée',
            'image' => UploadedFile::fake()->image('replacement.png', 200, 200),
            'primary_color' => '#0F172A',
            'secondary_color' => '#38BDF8',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lockIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match(
                '/select \* from [`"]avatars[`"] where [`"]avatars[`"]\.[`"]id[`"] = \?.*for update/i',
                $sql,
            ) === 1,
        );
        $updateIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match('/update [`"]avatars[`"] set /i', $sql) === 1,
        );

        expect($lockIndex)->not->toBeFalse()
            ->and($updateIndex)->not->toBeFalse()
            ->and($lockIndex)->toBeLessThan($updateIndex);
    }

    public function test_avatar_fields_and_uploaded_image_are_required_and_validated(): void
    {
        expect(Route::has('admin.avatars.store'))->toBeTrue();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.avatars.store'), [
            'name' => '',
            'image' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
            'primary_color' => 'violet',
            'secondary_color' => '#1234',
        ])->assertSessionHasErrors(['name', 'image', 'primary_color', 'secondary_color']);

        $this->assertDatabaseCount('avatars', 0);
    }

    public function test_avatar_image_respects_file_size_and_dimension_boundaries(): void
    {
        $admin = User::factory()->admin()->create();
        $oversizedDimensions = UploadedFile::fake()->image('wide.png', 1201, 100);
        $smallImage = UploadedFile::fake()->image('large.png', 100, 100);
        $oversizedFile = UploadedFile::fake()->createWithContent(
            'large.png',
            file_get_contents($smallImage->getPathname()).str_repeat('x', 2_100_000),
        );
        $payload = [
            'name' => 'Limite',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
        ];

        $this->actingAs($admin)->post(route('admin.avatars.store'), [
            ...$payload,
            'image' => $oversizedDimensions,
        ])->assertSessionHasErrors('image');
        $this->actingAs($admin)->post(route('admin.avatars.store'), [
            ...$payload,
            'image' => $oversizedFile,
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('avatars', 0);
    }

    public function test_avatar_image_is_only_delivered_to_authenticated_members(): void
    {
        $avatar = $this->avatar();

        $this->get(route('avatars.image', $avatar))
            ->assertRedirect(route('login'));

        $response = $this->actingAs(User::factory()->create())
            ->get(route('avatars.image', $avatar))
            ->assertOk();

        expect($response->streamedContent())->toBe('image');
    }

    public function test_admin_can_archive_reorder_and_delete_an_unused_avatar(): void
    {
        expect(Route::has('admin.avatars.status'))->toBeTrue()
            ->and(Route::has('admin.avatars.move'))->toBeTrue();

        $first = $this->avatar(['name' => 'Premier', 'sort_order' => 10]);
        $second = $this->avatar(['name' => 'Second', 'sort_order' => 20]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.avatars.status', $first), [
            'is_active' => false,
        ])->assertRedirect();
        expect($first->fresh()->is_active)->toBeFalse();

        $this->actingAs($admin)->patch(route('admin.avatars.move', $second), [
            'direction' => 'up',
        ])->assertRedirect();
        expect(Avatar::query()->orderBy('sort_order')->pluck('id')->all())
            ->toBe([$second->id, $first->id]);

        $path = $second->image_path;
        $this->actingAs($admin)
            ->delete(route('admin.avatars.destroy', $second))
            ->assertRedirect();
        $this->assertDatabaseMissing('avatars', ['id' => $second->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_selected_avatar_cannot_be_deleted(): void
    {
        expect(Route::has('admin.avatars.destroy'))->toBeTrue();

        $avatar = $this->avatar();
        User::factory()->withProfile()->create()->profile->update(['avatar_id' => $avatar->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.avatars.destroy', $avatar))
            ->assertSessionHasErrors('avatar');

        $this->assertDatabaseHas('avatars', ['id' => $avatar->id]);
        Storage::disk('local')->assertExists($avatar->image_path);
    }

    public function test_storage_cleanup_failure_is_reported_after_deleting_an_unused_avatar(): void
    {
        $avatar = $this->avatar();
        $admin = User::factory()->admin()->create();
        Exceptions::fake();
        Storage::shouldReceive('delete')
            ->once()
            ->with($avatar->image_path)
            ->andReturnFalse();

        $this->actingAs($admin)
            ->delete(route('admin.avatars.destroy', $avatar))
            ->assertRedirect();

        $this->assertDatabaseMissing('avatars', ['id' => $avatar->id]);
        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => str_contains(
                $exception->getMessage(),
                $avatar->image_path,
            ),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function avatar(array $attributes = []): Avatar
    {
        $avatar = Avatar::query()->create([
            'name' => 'Aurore',
            'image_path' => 'avatars/'.fake()->uuid().'.png',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
            'is_active' => true,
            'sort_order' => 0,
            ...$attributes,
        ]);
        Storage::disk('local')->put($avatar->image_path, 'image');

        return $avatar;
    }
}
