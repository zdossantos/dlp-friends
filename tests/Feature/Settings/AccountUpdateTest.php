<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_member_can_open_account_settings(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)->get(route('account.edit'))->assertOk();
    }

    public function test_member_can_update_only_their_account_email(): void
    {
        $user = User::factory()->withProfile()->create();
        $originalDisplayName = $user->profile->display_name;

        $response = $this->actingAs($user)->patch(route('account.update'), [
            'email' => 'updated@example.com',
            'display_name' => 'Injected Name',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
        $this->assertSame('updated@example.com', $user->refresh()->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($originalDisplayName, $user->profile->fresh()->display_name);
    }

    public function test_verification_is_unchanged_when_email_is_unchanged(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->patch(route('account.update'), ['email' => $user->email])
            ->assertRedirect(route('account.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_member_can_delete_their_account(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_is_required_to_delete_the_account(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->from(route('account.edit'))
            ->delete(route('account.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('account.edit'));

        $this->assertNotNull($user->fresh());
    }
}
