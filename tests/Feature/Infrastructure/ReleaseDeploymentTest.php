<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/dlp-release-'.bin2hex(random_bytes(8));
    mkdir($this->directory);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    $this->api = Process::env(['REQUEST_LOG' => $this->directory.'/requests.jsonl'])
        ->start([PHP_BINARY, '-S', $address, base_path('tests/Support/coolify-api-router.php')]);

    retry(50, function () use ($address) {
        $connection = @stream_socket_client('tcp://'.$address);
        if (! $connection) {
            throw new RuntimeException('Waiting for the local Coolify API fixture.');
        }
        fclose($connection);
    }, 20);

    $this->deploymentEnvironment = [
        'RELEASE_CREATED' => 'true',
        'RELEASE_SHA' => str_repeat('b', 40),
        'APP_IMAGE' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
        'COOLIFY_API_URL' => 'http://'.$address.'/api/v1',
        'COOLIFY_APPLICATION_UUID' => 'application-test',
        'COOLIFY_TOKEN' => 'test-only-token',
    ];
});

afterEach(function () {
    $this->api->stop();
    File::deleteDirectory($this->directory);
});

function recordedCoolifyRequests(string $directory): array
{
    if (! file_exists($directory.'/requests.jsonl')) {
        return [];
    }

    return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        file($directory.'/requests.jsonl', FILE_IGNORE_NEW_LINES));
}

it('pins the image and Compose revision before requesting deployment', function () {
    $result = Process::env($this->deploymentEnvironment)->run(['bash', base_path('.github/scripts/deploy-release.sh')]);

    expect($result->successful())->toBeTrue($result->errorOutput());
    $requests = recordedCoolifyRequests($this->directory);
    expect(array_column($requests, 'method'))->toBe(['PATCH', 'PATCH', 'POST'])
        ->and(array_column($requests, 'path'))->toBe([
            '/api/v1/applications/application-test/envs/bulk',
            '/api/v1/applications/application-test',
            '/api/v1/deploy',
        ])
        ->and($requests[0]['body']['data'])->toBe([[
            'key' => 'APP_IMAGE',
            'value' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
            'is_preview' => false,
            'is_literal' => true,
            'is_buildtime' => true,
            'is_runtime' => true,
        ]])
        ->and($requests[1]['body'])->toBe(['git_commit_sha' => str_repeat('b', 40)])
        ->and($requests[2]['body'])->toBe(['uuid' => 'application-test', 'force' => false]);

    foreach ($requests as $request) {
        expect($request['authorization'])->toBe('Bearer test-only-token');
    }
});

it('does not contact Coolify without a new release', function () {
    $result = Process::env(array_merge($this->deploymentEnvironment, ['RELEASE_CREATED' => 'false']))
        ->run(['bash', base_path('.github/scripts/deploy-release.sh')]);

    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(recordedCoolifyRequests($this->directory))->toBe([]);
});

it('refuses deployment without an immutable published image or exact revision', function (array $override) {
    $result = Process::env(array_merge($this->deploymentEnvironment, $override))
        ->run(['bash', base_path('.github/scripts/deploy-release.sh')]);

    expect($result->failed())->toBeTrue()
        ->and(recordedCoolifyRequests($this->directory))->toBe([]);
})->with([
    'publication failed' => [['APP_IMAGE' => '']],
    'mutable tag' => [['APP_IMAGE' => 'ghcr.io/zdossantos/dlp-friends:latest']],
    'missing release revision' => [['RELEASE_SHA' => '']],
]);

it('stops on a Coolify error without leaking its response', function (string $path, int $requestCount) {
    file_put_contents($this->directory.'/failure-path', $path);
    $result = Process::env($this->deploymentEnvironment)->run(['bash', base_path('.github/scripts/deploy-release.sh')]);

    expect($result->failed())->toBeTrue()
        ->and(recordedCoolifyRequests($this->directory))->toHaveCount($requestCount)
        ->and($result->output().$result->errorOutput())->not->toContain('private-response');
})->with([
    'image update rejected' => ['/api/v1/applications/application-test/envs/bulk', 1],
    'revision update rejected' => ['/api/v1/applications/application-test', 2],
    'deployment rejected' => ['/api/v1/deploy', 3],
]);
