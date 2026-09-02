<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

function productionComposeEnvironment(array $overrides = []): array
{
    return array_merge([
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'APP_URL' => 'https://dlpfriends.example',
        'LEGAL_CONTACT_EMAIL' => 'legal@dlpfriends.example',
        'DB_DATABASE' => 'dlp_friends',
        'DB_USERNAME' => 'dlp_friends',
        'DB_PASSWORD' => 'database-password',
        'MYSQL_ROOT_PASSWORD' => 'root-password',
        'MINIO_ROOT_USER' => 'dlp-friends',
        'MINIO_ROOT_PASSWORD' => 'minio-root-password',
        'AWS_ACCESS_KEY_ID' => 'dlp-friends',
        'AWS_SECRET_ACCESS_KEY' => 'minio-application-password',
        'AWS_BUCKET' => 'dlp-friends',
        'REVERB_APP_ID' => 'dlp-friends-production',
        'REVERB_APP_KEY' => 'reverb-public-key',
        'REVERB_APP_SECRET' => 'reverb-secret',
        'VITE_REVERB_HOST' => 'reverb.dlpfriends.example',
        'RESEND_API_KEY' => 're_test_only',
        'MAIL_FROM_ADDRESS' => 'noreply@dlpfriends.example',
    ], $overrides);
}

function resolveProductionCompose(array $overrides = []): ProcessResult
{
    return Process::path(base_path())
        ->env(productionComposeEnvironment($overrides))
        ->run('docker compose -f compose.production.yaml config --format json');
}

it('resolves the production service topology without local-only services', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($compose['services']))
        ->toEqualCanonicalizing(['web', 'worker', 'scheduler', 'reverb', 'mysql', 'redis', 'minio']);
});

it('reuses one application image for every long-running Laravel process', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $services = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['services'];
    $applicationServices = ['web', 'worker', 'scheduler', 'reverb'];
    $images = array_map(fn (string $service): string => $services[$service]['image'], $applicationServices);

    expect(array_unique($images))->toHaveCount(1)
        ->and($services['web'])->toHaveKey('build')
        ->and($services['web']['command'])->toBe(['web'])
        ->and($services['worker']['command'])->toBe(['worker'])
        ->and($services['scheduler']['command'])->toBe(['scheduler'])
        ->and($services['reverb']['command'])->toBe(['reverb']);
});

it('keeps data services private and persists durable production data', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    foreach (['mysql', 'redis', 'minio'] as $service) {
        expect($compose['services'][$service])->not->toHaveKey('ports');
    }

    expect($compose['services']['mysql']['volumes'][0]['target'])->toBe('/var/lib/mysql')
        ->and($compose['services']['minio']['volumes'][0]['target'])->toBe('/data')
        ->and(array_keys($compose['volumes']))->toEqualCanonicalizing(['mysql-data', 'minio-data']);
});

it('configures healthchecks and Resend without automatic migrations', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $services = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['services'];

    foreach ($services as $service) {
        expect($service)->toHaveKey('healthcheck');
    }

    foreach (['web', 'worker', 'scheduler', 'reverb'] as $service) {
        expect($services[$service]['environment']['MAIL_MAILER'])->toBe('resend')
            ->and($services[$service]['environment']['RESEND_API_KEY'])->toBe('re_test_only')
            ->and(implode(' ', $services[$service]['command']))->not->toContain('migrate');
    }
});

it('rejects a production deployment when a critical variable is missing', function (string $variable) {
    $result = resolveProductionCompose([$variable => '']);

    expect($result->failed())->toBeTrue()
        ->and($result->errorOutput())->toContain($variable);
})->with([
    'Laravel application key' => 'APP_KEY',
    'database password' => 'DB_PASSWORD',
    'Reverb secret' => 'REVERB_APP_SECRET',
    'Resend key' => 'RESEND_API_KEY',
]);

it('builds the Laravel Resend transport used in production', function () {
    config()->set('services.resend.key', 're_test_only');
    app('mail.manager')->forgetMailers();

    $transport = app('mail.manager')->mailer('resend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransport::class);
});
