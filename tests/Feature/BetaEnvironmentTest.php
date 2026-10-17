<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

function configureSafeBeta(): void
{
    app()->instance('env', 'beta');
    app('session')->extend('redis', fn () => new ArraySessionHandler(120));
    config()->set('cache.stores.redis.driver', 'array');
    config()->set([
        'app.debug' => false,
        'app.url' => 'https://beta.dlp-friends.fr',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'mailpit-beta',
        'mail.mailers.smtp.port' => 1025,
        'mail.mailers.smtp.url' => null,
        'deployment.beta_mail_host' => 'mailpit-beta',
        'services.resend.key' => null,
        'session.driver' => 'redis',
        'session.domain' => null,
        'session.secure' => true,
        'cache.default' => 'redis',
        'queue.default' => 'redis',
    ]);
}

it('keeps destructive commands and weak passwords prohibited in beta', function () {
    configureSafeBeta();
    app(AppServiceProvider::class, ['app' => app()])->boot();

    $this->artisan('migrate:rollback', ['--force' => true, '--pretend' => true])->assertFailed();
    expect(Validator::make(['password' => 'short'], ['password' => Password::defaults()])->fails())->toBeTrue();
});

it('rejects unsafe beta runtime configuration', function (string $key, mixed $value) {
    configureSafeBeta();
    config()->set($key, $value);

    $this->artisan('deployment:check', ['--json' => true])->assertFailed();
    $this->get('/fr')->assertServiceUnavailable();
})->with([
    'real mail provider' => ['mail.default', 'resend'],
    'external SMTP' => ['mail.mailers.smtp.host', 'smtp.example.org'],
    'SMTP URL bypass' => ['mail.mailers.smtp.url', 'smtp://external.example.org'],
    'Resend key present' => ['services.resend.key', 'private-key'],
    'debug enabled' => ['app.debug', true],
    'shared cookies' => ['session.domain', '.dlp-friends.fr'],
    'insecure cookies' => ['session.secure', false],
]);

it('does not index or track beta even if analytics variables were accidentally copied', function () {
    configureSafeBeta();
    config()->set('services.google.analytics_id', 'G-TEST123');
    config()->set('services.google.site_verification', 'private-site-verification');

    $this->get('/fr')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertDontSee('G-TEST123')->assertDontSee('private-site-verification');
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
});
