<?php

namespace Tests\Unit\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_age_is_calculated_from_the_birth_date(): void
    {
        Carbon::setTestNow('2026-08-16');

        $this->assertSame(18, (new User(['birth_date' => '2008-08-16']))->age);
        $this->assertSame(26, (new User(['birth_date' => '2000-08-15']))->age);
        $this->assertSame(25, (new User(['birth_date' => '2000-08-17']))->age);
    }

    public function test_age_is_null_when_birth_date_is_missing(): void
    {
        $this->assertNull((new User)->age);
    }

    public function test_factory_users_are_adult_and_active_by_default(): void
    {
        Carbon::setTestNow('2026-08-16');

        $user = User::factory()->create();

        $this->assertGreaterThanOrEqual(18, $user->age);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    public function test_user_can_check_an_assigned_role(): void
    {
        $user = User::factory()->create();
        $admin = Role::query()->where('name', RoleName::Admin)->firstOrFail();
        $user->roles()->attach($admin);

        $this->assertTrue($user->fresh('roles')->hasRole(RoleName::Admin));
        $this->assertTrue($user->hasRole(RoleName::User));
    }
}
