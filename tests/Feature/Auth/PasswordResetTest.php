<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
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

    public function test_password_reset_emails_are_limited_per_address(): void
    {
        Mail::fake();
        config()->set('auth.passwords.users.throttle', 0);

        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->withCookie('locale', 'fr')
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors([
                'email' => 'mail.rate_limited',
            ]);

        Mail::assertSentCount(3);
    }

    public function test_password_reset_emails_are_limited_per_ip_across_addresses(): void
    {
        Mail::fake();
        config()->set('auth.passwords.users.throttle', 0);

        $users = User::factory()->count(11)->create();

        foreach ($users->take(10) as $user) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => $users->last()->email])
            ->assertSessionHasErrors('email');

        Mail::assertSentCount(10);
    }

    public function test_password_reset_mail_transport_errors_are_returned_safely(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('SMTP credentials exposed here'));

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors([
                'email' => 'mail.delivery_failed',
            ]);
    }

    public function test_password_reset_link_confirmation_is_translated_in_french(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas(
                'status',
                'Le lien de réinitialisation de ton mot de passe vient de t’être envoyé par e-mail.',
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
