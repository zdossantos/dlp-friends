<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->sshDirectory = sys_get_temp_dir().'/dlp-beta-ssh-'.bin2hex(random_bytes(8));
    mkdir($this->sshDirectory, 0700);
    $this->sshConfig = $this->sshDirectory.'/config.json';
    file_put_contents($this->sshConfig, json_encode([
        'application_uuid' => 'beta-fixed', 'production_application_uuid' => 'production-fixed',
        'lock_file' => $this->sshDirectory.'/deployment.lock',
    ]));
    chmod($this->sshConfig, 0600);
    $this->sshPayload = [
        'application_uuid' => 'beta-fixed', 'sha' => str_repeat('b', 40),
        'image' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
    ];
    file_put_contents($this->sshDirectory.'/docker', <<<'SH'
#!/bin/sh
printf '%s\n' "$@" > "$DOCKER_LOG"
printf '{"deployment_uuid":"beta-test"}\n'
SH);
    chmod($this->sshDirectory.'/docker', 0700);
});

afterEach(fn () => File::deleteDirectory($this->sshDirectory));

function betaSshRequest($test, string $input): ProcessResult
{
    return Process::env([
        'PATH' => $test->sshDirectory.':'.getenv('PATH'),
        'DOCKER_LOG' => $test->sshDirectory.'/docker.log',
    ])->input($input)->run(['python3', base_path('scripts/operations/beta-ssh-deploy.py'), '--config', $test->sshConfig]);
}

it('refuses malformed or production SSH requests before invoking Coolify', function (array $changes) {
    $result = betaSshRequest($this, json_encode(array_merge($this->sshPayload, $changes)));
    expect($result->failed())->toBeTrue()
        ->and(file_exists($this->sshDirectory.'/docker.log'))->toBeFalse();
})->with([
    'production UUID' => [['application_uuid' => 'production-fixed']],
    'unknown field' => [['command' => 'artisan migrate --force']],
    'other image' => [['image' => 'ghcr.io/other/app@sha256:'.str_repeat('a', 64)]],
    'mutable image' => [['image' => 'ghcr.io/zdossantos/dlp-friends:latest']],
    'shell SHA' => [['sha' => '$(touch /tmp/unsafe)']],
]);

it('bounds SSH input size before invoking Coolify', function () {
    $result = betaSshRequest($this, str_repeat('x', 4097));
    expect($result->failed())->toBeTrue()
        ->and(file_exists($this->sshDirectory.'/docker.log'))->toBeFalse();
});

it('refuses an insecure operator config', function () {
    chmod($this->sshConfig, 0644);
    $result = betaSshRequest($this, json_encode($this->sshPayload));
    expect($result->failed())->toBeTrue()
        ->and(file_exists($this->sshDirectory.'/docker.log'))->toBeFalse();
});

it('passes the validated beta request to the fixed Coolify runtime', function () {
    $result = betaSshRequest($this, json_encode($this->sshPayload));
    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(json_decode($result->output(), true))->toBe(['deployment_uuid' => 'beta-test']);
    $arguments = file($this->sshDirectory.'/docker.log', FILE_IGNORE_NEW_LINES);
    expect(array_slice($arguments, 0, 7))->toBe(['exec', '-u', '0', 'coolify', 'php', 'artisan', 'tinker']);
    $php = substr($arguments[7], strlen('--execute='));
    file_put_contents($this->sshDirectory.'/command.php', '<?php '.$php);
    $lint = Process::run([PHP_BINARY, '-l', $this->sshDirectory.'/command.php']);
    expect($lint->successful())->toBeTrue($lint->errorOutput());
});

it('checks the actual Coolify application and active queue before any write', function (array $scenario, bool $allowed) {
    file_put_contents($this->sshDirectory.'/docker', '#!/bin/sh'."\n".'shift 7'."\n".'exec '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('tests/Support/beta-coolify-runtime.php')).' "$@"'."\n");
    $environment = array_merge([
        'PATH' => $this->sshDirectory.':'.getenv('PATH'),
        'WRITE_LOG' => $this->sshDirectory.'/writes.jsonl',
    ], $scenario);
    $result = Process::env($environment)->input(json_encode($this->sshPayload))
        ->run(['python3', base_path('scripts/operations/beta-ssh-deploy.py'), '--config', $this->sshConfig]);
    expect($result->successful())->toBe($allowed, $result->errorOutput());
    if (! $allowed) {
        expect(file_exists($this->sshDirectory.'/writes.jsonl'))->toBeFalse();

        return;
    }
    $writes = array_map(fn ($line) => json_decode($line, true), file($this->sshDirectory.'/writes.jsonl'));
    expect($writes)->toBe([
        ['env' => ['key' => 'APP_IMAGE', 'is_preview' => false], 'values' => [
            'value' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
            'is_literal' => true, 'is_buildtime' => true, 'is_runtime' => true,
        ]],
        ['sha' => str_repeat('b', 40), 'branch' => 'chore/beta-candidates/'.str_repeat('b', 40)], ['deployment_uuid' => 'deployment-fixed'],
    ]);
})->with([
    'beta ready' => [[], true],
    'production environment' => [['FIXTURE_ENVIRONMENT' => 'production'], false],
    'other repository' => [['FIXTURE_REPOSITORY' => 'other/repository'], false],
    'deployment active' => [['FIXTURE_ACTIVE' => 'yes'], false],
]);

it('refuses success when Coolify does not create the requested deployment', function () {
    file_put_contents($this->sshDirectory.'/docker', '#!/bin/sh'."\n".'shift 7'."\n".'exec '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('tests/Support/beta-coolify-runtime.php')).' "$@"'."\n");
    $result = Process::env([
        'PATH' => $this->sshDirectory.':'.getenv('PATH'),
        'WRITE_LOG' => $this->sshDirectory.'/writes.jsonl',
        'FIXTURE_QUEUE_FULL' => 'yes',
    ])->input(json_encode($this->sshPayload))->run(['python3', base_path('scripts/operations/beta-ssh-deploy.py'), '--config', $this->sshConfig]);
    expect($result->failed())->toBeTrue()
        ->and($result->output())->toBe('');
});

it('does not expose Coolify failure details to the SSH caller', function () {
    file_put_contents($this->sshDirectory.'/docker', "#!/bin/sh\necho 'private-runtime-secret' >&2\nexit 1\n");
    $result = betaSshRequest($this, json_encode($this->sshPayload));
    expect($result->failed())->toBeTrue()
        ->and($result->output().$result->errorOutput())->not->toContain('private-runtime-secret');
});
