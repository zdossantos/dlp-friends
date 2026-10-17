<?php

use Illuminate\Support\Facades\Process;

function betaComposeEnvironment(array $overrides = []): array
{
    return array_merge([
        'APP_IMAGE' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('b', 64),
        'BETA_APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'BETA_LEGAL_CONTACT_EMAIL' => 'operator@example.test',
        'BETA_DB_HOST' => 'mysql-beta.internal', 'BETA_DB_DATABASE' => 'beta',
        'BETA_DB_USERNAME' => 'beta', 'BETA_DB_PASSWORD' => 'private-test-password',
        'BETA_REDIS_HOST' => 'redis-beta.internal', 'BETA_REDIS_PASSWORD' => 'private-test-redis',
        'BETA_AWS_ENDPOINT' => 'http://garage.internal:3900', 'BETA_AWS_BUCKET' => 'beta',
        'BETA_AWS_ACCESS_KEY_ID' => 'beta-key', 'BETA_AWS_SECRET_ACCESS_KEY' => 'private-test-storage',
        'BETA_MAILPIT_HOST' => 'mailpit-beta.internal',
        'BETA_REVERB_APP_ID' => 'beta', 'BETA_REVERB_APP_KEY' => 'beta-public',
        'BETA_REVERB_APP_SECRET' => 'private-test-reverb',
        'BETA_VAPID_SUBJECT' => 'mailto:operator@example.test',
        'BETA_VAPID_PUBLIC_KEY' => 'beta-public', 'BETA_VAPID_PRIVATE_KEY' => 'private-test-push',
    ], $overrides);
}

it('renders four isolated beta processes with private mail and stable maintenance', function () {
    $result = Process::env(betaComposeEnvironment())->run('docker compose -f compose.beta.yaml config --format json');
    expect($result->successful())->toBeTrue($result->errorOutput());
    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    expect(array_keys($compose['services']))->toEqualCanonicalizing(['web', 'worker', 'scheduler', 'reverb']);
    foreach ($compose['services'] as $service) {
        expect($service)->not->toHaveKey('ports');
        expect($service['environment'])->toMatchArray([
            'APP_ENV' => 'beta', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://beta.dlp-friends.fr',
            'APP_MAINTENANCE_DRIVER' => 'cache', 'APP_MAINTENANCE_STORE' => 'redis',
            'CACHE_PREFIX' => 'dlp_friends_beta_cache_', 'REDIS_PREFIX' => 'dlp_friends_beta_',
            'MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'mailpit-beta.internal', 'MAIL_PORT' => '1025',
            'DB_HOST' => 'mysql-beta.internal', 'REDIS_HOST' => 'redis-beta.internal',
            'AWS_BUCKET' => 'beta', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'true',
            'REVERB_HOST' => 'dlp-friends-beta-reverb',
        ])->not->toHaveKey('RESEND_API_KEY');
    }
    expect($compose['services']['reverb']['networks']['default']['aliases'])
        ->toContain('dlp-friends-beta-reverb');
    // Coolify adds explicit networks, removing Compose's implicit default membership.
    $raw = Process::env(betaComposeEnvironment())->run('docker compose -f compose.beta.yaml config --no-normalize --no-consistency --format json');
    expect($raw->successful())->toBeTrue($raw->errorOutput());
    $source = json_decode($raw->output(), true, flags: JSON_THROW_ON_ERROR);
    foreach ($source['services'] as $service) {
        expect($service['networks'] ?? [])->toHaveKey('default');
    }
});

it('refuses beta rendering with any missing critical isolated resource', function (string $variable) {
    $result = Process::env(betaComposeEnvironment([$variable => '']))->run('docker compose -f compose.beta.yaml config --format json');
    expect($result->failed())->toBeTrue()->and($result->errorOutput())->toContain($variable);
})->with(['APP_IMAGE', 'BETA_APP_KEY', 'BETA_DB_HOST', 'BETA_DB_DATABASE', 'BETA_DB_PASSWORD', 'BETA_REDIS_HOST', 'BETA_REDIS_PASSWORD', 'BETA_AWS_BUCKET', 'BETA_AWS_SECRET_ACCESS_KEY', 'BETA_MAILPIT_HOST']);

it('blocks background processes until readiness succeeds without migrating', function (string $process, bool $ready) {
    $directory = sys_get_temp_dir().'/dlp-entrypoint-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $entrypoint = str_replace('cd /var/www/html', 'cd '.escapeshellarg($directory), file_get_contents(base_path('docker/php/entrypoint.sh')));
    file_put_contents($directory.'/entrypoint.sh', $entrypoint);
    file_put_contents($directory.'/php', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$ENTRYPOINT_LOG"
if [ "$2" = deployment:check ]; then exit "$READINESS_CODE"; fi
exit 0
SH);
    chmod($directory.'/php', 0700);
    try {
        $result = Process::env([
            'PATH' => $directory.':'.getenv('PATH'), 'ENTRYPOINT_LOG' => $directory.'/calls',
            'READINESS_CODE' => $ready ? '0' : '1', 'DEPLOYMENT_READINESS_ATTEMPTS' => '1',
        ])->run(['sh', $directory.'/entrypoint.sh', $process]);
        $calls = file_get_contents($directory.'/calls');
        expect($calls)->toContain('deployment:check')->not->toContain('migrate');
        expect($result->successful())->toBe($ready);
        $command = ['worker' => 'queue:work', 'scheduler' => 'schedule:work', 'reverb' => 'reverb:start'][$process];
        expect(str_contains($calls, $command))->toBe($ready);
    } finally {
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
})->with(['worker', 'scheduler', 'reverb'])->with([false, true]);
