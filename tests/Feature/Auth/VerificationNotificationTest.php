<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        Mail::assertSent(VerifyEmailMail::class, $user->email);
    }

    public function test_verification_emails_are_limited_per_account(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create(['locale' => 'fr']);

        foreach (range(1, 3) as $_) {
            $this->actingAs($user)
                ->post(route('verification.send'))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->post(route('verification.send'))
            ->assertSessionHasErrors([
                'email' => 'mail.rate_limited',
            ]);

        Mail::assertSentCount(3);
    }

    public function test_verification_mail_transport_errors_are_returned_safely_in_english(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('SMTP credentials exposed here'));

        $user = User::factory()->unverified()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->post(route('verification.send'))
            ->assertSessionHasErrors([
                'email' => 'mail.delivery_failed',
            ]);
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect('/app');

        Mail::assertNothingSent();
    }

    public function test_uses_the_account_locale_for_the_verification_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->post(route('verification.send'));

        Mail::assertSent(
            VerifyEmailMail::class,
            fn (VerifyEmailMail $mail): bool => $mail->locale === 'en',
        );
    }

    public function test_uses_the_browser_locale_when_the_account_has_no_preference(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create(['locale' => null]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->post(route('verification.send'));

        Mail::assertSent(
            VerifyEmailMail::class,
            fn (VerifyEmailMail $mail): bool => $mail->locale === 'en',
        );
    }
}
