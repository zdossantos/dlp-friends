<?php

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function productionComposeEnvironment(array $overrides = []): array
{
    return array_merge([
        'APP_IMAGE' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'APP_URL' => 'https://dlpfriends.example',
        'LEGAL_CONTACT_EMAIL' => 'legal@dlpfriends.example',
        'DB_DATABASE' => 'dlp_friends',
        'DB_USERNAME' => 'dlp_friends',
        'DB_PASSWORD' => 'database-password',
        'DB_HOST' => 'mysql-independent.internal',
        'MYSQL_NETWORK' => 'mysql-private-test',
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
        ->toEqualCanonicalizing(['web', 'worker', 'scheduler', 'reverb', 'redis', 'minio']);
});

it('reuses one application image for every long-running Laravel process', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $services = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['services'];
    $applicationServices = ['web', 'worker', 'scheduler', 'reverb'];
    $images = array_map(fn (string $service): string => $services[$service]['image'], $applicationServices);

    expect(array_unique($images))->toHaveCount(1)
        ->and($images[0])->toBe(productionComposeEnvironment()['APP_IMAGE'])
        ->and($services['web']['command'])->toBe(['web'])
        ->and($services['worker']['command'])->toBe(['worker'])
        ->and($services['scheduler']['command'])->toBe(['scheduler'])
        ->and($services['reverb']['command'])->toBe(['reverb']);

    foreach ($applicationServices as $service) {
        expect($services[$service])->not->toHaveKey('build');
    }
});

it('keeps data services private and persists durable production data', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    foreach (['redis', 'minio'] as $service) {
        expect($compose['services'][$service])->not->toHaveKey('ports');
    }

    expect($compose['services']['minio']['volumes'][0]['target'])->toBe('/data')
        ->and(array_keys($compose['volumes']))->toEqualCanonicalizing(['minio-data']);
});

it('uses collision-resistant aliases for private production services', function () {
    $result = resolveProductionCompose();

    expect($result->successful())->toBeTrue($result->errorOutput());

    $services = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['services'];

    foreach (['web', 'worker', 'scheduler', 'reverb'] as $service) {
        expect($services[$service]['environment']['REDIS_HOST'])->toBe('dlp-friends-redis')
            ->and($services[$service]['environment']['REVERB_HOST'])->toBe('dlp-friends-reverb')
            ->and($services[$service]['environment']['AWS_ENDPOINT'])->toBe('http://dlp-friends-minio:9000');
    }

    expect($services['redis']['networks']['dlp-friends']['aliases'])->toContain('dlp-friends-redis')
        ->and($services['reverb']['networks']['dlp-friends']['aliases'])->toContain('dlp-friends-reverb')
        ->and($services['minio']['networks']['dlp-friends']['aliases'])->toContain('dlp-friends-minio');
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
    'published application image' => 'APP_IMAGE',
    'Laravel application key' => 'APP_KEY',
    'database password' => 'DB_PASSWORD',
    'external database host' => 'DB_HOST',
    'external database network' => 'MYSQL_NETWORK',
    'Reverb secret' => 'REVERB_APP_SECRET',
    'Resend key' => 'RESEND_API_KEY',
]);

it('builds the Laravel Resend transport used in production', function () {
    config()->set('services.resend.key', 're_test_only');
    app('mail.manager')->forgetMailers();

    $transport = app('mail.manager')->mailer('resend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransport::class);
});

it('builds the S3 filesystem used for production object storage', function () {
    $disk = Storage::build([
        'driver' => 's3',
        'key' => 'dlp-friends',
        'secret' => 'minio-application-password',
        'region' => 'us-east-1',
        'bucket' => 'dlp-friends',
        'endpoint' => 'http://minio:9000',
        'use_path_style_endpoint' => true,
    ]);

    expect($disk)->toBeInstanceOf(AwsS3V3Adapter::class);
});

it('honors the HTTPS scheme forwarded by the Coolify proxy', function () {
    $this->withHeaders([
        'X-Forwarded-For' => '203.0.113.10',
        'X-Forwarded-Host' => 'dlp-friends.fr',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-Proto' => 'https',
    ])->get('/')
        ->assertRedirect('https://dlp-friends.fr/fr');
});

it('connects every Laravel process to configurable external MySQL without server credentials', function () {
    $result = resolveProductionCompose(['DB_PORT' => '3308', 'MYSQL_ROOT_PASSWORD' => '']);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    expect($compose['networks']['mysql-private']['external'])->toBeTrue()
        ->and($compose['networks']['mysql-private']['name'])->toBe('mysql-private-test');

    foreach (['web', 'worker', 'scheduler', 'reverb'] as $service) {
        expect($compose['services'][$service]['environment']['DB_HOST'])->toBe('mysql-independent.internal')
            ->and((string) $compose['services'][$service]['environment']['DB_PORT'])->toBe('3308')
            ->and($compose['services'][$service]['environment'])->not->toHaveKey('MYSQL_ROOT_PASSWORD')
            ->and($compose['services'][$service]['depends_on'])->not->toHaveKey('mysql')
            ->and($compose['services'][$service]['networks'])->toHaveKey('mysql-private');
    }
});
