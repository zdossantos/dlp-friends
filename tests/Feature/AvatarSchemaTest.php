<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AvatarSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_catalog_and_profile_selection_are_persisted(): void
    {
        expect(Schema::hasColumns('avatars', [
            'id',
            'name',
            'image_path',
            'primary_color',
            'secondary_color',
            'is_active',
            'sort_order',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
            ->and(Schema::hasColumn('profiles', 'avatar_id'))->toBeTrue();
    }
}
