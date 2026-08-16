<?php

namespace Tests\Feature\Auth;

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
            'username' => '  Magic   Friend  ',
            'email' => 'test@example.com',
            'birth_date' => '2008-08-16',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'Magic Friend',
            'email' => 'test@example.com',
            'birth_date' => '2008-08-16 00:00:00',
            'status' => UserStatus::Active->value,
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_birth_date_is_required(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'Magic Friend',
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
            'username' => 'Magic Friend',
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

    public function test_username_is_required(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'email' => 'test@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'Magic Friend']);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'Magic Friend',
            'email' => 'other@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_username_rejects_unsupported_punctuation(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'Magic@Friend',
            'email' => 'test@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_username_must_have_at_least_three_characters(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'AB',
            'email' => 'test@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_username_cannot_exceed_thirty_characters(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => str_repeat('A', 31),
            'email' => 'test@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
