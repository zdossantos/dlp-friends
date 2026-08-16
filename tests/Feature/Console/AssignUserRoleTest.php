<?php

namespace Tests\Feature\Console;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_role_can_be_assigned_idempotently_by_email(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:assign-role', [
            'email' => $user->email,
            'role' => RoleName::Admin->value,
        ])->assertSuccessful();

        $this->artisan('user:assign-role', [
            'email' => $user->email,
            'role' => RoleName::Admin->value,
        ])->assertSuccessful();

        $this->assertTrue($user->fresh('roles')->hasRole(RoleName::Admin));
        $this->assertDatabaseCount('user_roles', 2);
    }

    public function test_unknown_account_fails_without_creating_an_assignment(): void
    {
        $this->artisan('user:assign-role', [
            'email' => 'missing@example.com',
            'role' => RoleName::Admin->value,
        ])->assertFailed();

        $this->assertDatabaseCount('user_roles', 0);
    }

    public function test_unknown_role_fails_without_creating_an_assignment(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:assign-role', [
            'email' => $user->email,
            'role' => 'super-admin',
        ])->assertFailed();

        $this->assertFalse($user->fresh('roles')->hasRole(RoleName::Admin));
    }
}
