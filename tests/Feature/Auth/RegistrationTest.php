<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        Carbon::setTestNow('2026-08-16');

        $response = $this->post(route('register.store'), [
            'email' => 'test@example.com',
            'birth_date' => '2008-08-16',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->username);
        $this->assertTrue($user->load('roles')->hasRole(RoleName::User));
        $this->assertNull($user->profile);
        $this->assertSame(UserStatus::Active, $user->status);
        $response->assertRedirect('/app');
    }

    public function test_birth_date_is_required(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('birth_date');
        $this->assertGuest();
    }

    public function test_users_younger_than_eighteen_cannot_register(): void
    {
        Carbon::setTestNow('2026-08-16');

        $response = $this->from(route('register'))->post(route('register.store'), [
            'email' => 'test@example.com',
            'birth_date' => '2008-08-17',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'birth_date' => 'Vous devez être majeur pour vous inscrire.',
        ]);
        $this->assertGuest();
    }
}
