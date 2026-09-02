<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SocialAccountSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_accounts_store_only_the_provider_identity(): void
    {
        $this->assertTrue(Schema::hasColumns('social_accounts', [
            'id',
            'user_id',
            'provider',
            'provider_user_id',
            'created_at',
            'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('social_accounts', 'token'));
        $this->assertFalse(Schema::hasColumn('social_accounts', 'refresh_token'));
    }

    public function test_google_provider_identity_is_unique(): void
    {
        SocialAccount::factory()->create([
            'provider' => 'google',
            'provider_user_id' => 'provider-123',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        SocialAccount::factory()->create([
            'provider' => 'google',
            'provider_user_id' => 'provider-123',
        ]);
    }

    public function test_social_accounts_are_deleted_with_their_user(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('social_accounts', 0);
    }
}
