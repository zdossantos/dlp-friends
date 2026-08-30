<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(ResetPasswordMail::class, $user->email);
    }

    public function test_password_reset_link_confirmation_is_translated_in_french(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas(
                'status',
                'Nous vous avons envoyé le lien de réinitialisation de votre mot de passe par e-mail.',
            );
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
            $response = $this->get(route('password.reset', $mail->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $mail->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_uses_the_account_locale_for_the_password_reset_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['locale' => 'en']);

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail): bool => $mail->locale === 'en',
        );
    }

    public function test_uses_the_browser_locale_when_the_account_has_no_preference(): void
    {
        Mail::fake();

        $user = User::factory()->create(['locale' => null]);

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail): bool => $mail->locale === 'en',
        );
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
