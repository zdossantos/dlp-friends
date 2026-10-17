<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->candidateDirectory = sys_get_temp_dir().'/dlp-beta-candidate-'.bin2hex(random_bytes(8));
    mkdir($this->candidateDirectory);
    file_put_contents($this->candidateDirectory.'/scenario.json', '{}');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $this->candidateApi = Process::env([
        'REQUEST_LOG' => $this->candidateDirectory.'/requests.jsonl',
        'SCENARIO_FILE' => $this->candidateDirectory.'/scenario.json',
    ])->start([PHP_BINARY, '-S', $address, base_path('tests/Support/github-api-router.php')]);
    retry(50, function () use ($address) {
        $connection = @stream_socket_client('tcp://'.$address);
        if (! $connection) {
            throw new RuntimeException('Waiting for local API fixture');
        }
        fclose($connection);
    }, 20);
    $this->candidateEnvironment = [
        'GITHUB_API_URL' => 'http://'.$address, 'GITHUB_REPOSITORY' => 'zdossantos/dlp-friends', 'GH_TOKEN' => 'fixture-token',
        'COOLIFY_API_URL' => 'http://'.$address.'/api/v1', 'COOLIFY_BETA_APPLICATION_UUID' => 'application-test',
        'COOLIFY_PRODUCTION_APPLICATION_UUID' => 'production-test', 'COOLIFY_TOKEN' => 'fixture-token',
        'APP_IMAGE' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
        'BETA_CANDIDATE_FILE' => $this->candidateDirectory.'/candidate.json',
    ];
    file_put_contents($this->candidateEnvironment['BETA_CANDIDATE_FILE'], json_encode([
        'number' => 268, 'sha' => str_repeat('b', 40), 'tree' => str_repeat('c', 40),
        'head_ref' => 'release-please--branches--main--components--dlp-friends',
    ]));
});

afterEach(function () {
    $this->candidateApi->stop();
    File::deleteDirectory($this->candidateDirectory);
});

it('retains beta without an eligible release PR or exact successful checks', function (array $scenario) {
    file_put_contents($this->candidateDirectory.'/scenario.json', json_encode($scenario));
    $result = Process::env($this->candidateEnvironment)->run(['bash', base_path('.github/scripts/select-beta-candidate.sh')]);
    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(trim($result->output()))->toBe('null')
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse();
    $deployment = Process::env($this->candidateEnvironment)->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($deployment->successful())->toBeTrue($deployment->errorOutput())
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse();
})->with([
    'no release PR' => [['no_pr' => true]],
    'fork' => [['pr' => ['head' => ['repo' => ['full_name' => 'fork/dlp-friends']]]]],
    'wrong author' => [['pr' => ['user' => ['login' => 'untrusted']]]],
    'wrong label' => [['pr' => ['labels' => [['name' => 'unrelated']]]]],
    'maintenance branch' => [['pr' => ['base' => ['ref' => 'release/1.14']]]],
    'draft' => [['pr' => ['draft' => true]]],
    'other head checks' => [['checks_sha' => str_repeat('a', 40)]],
    'CI failure' => [['conclusion' => 'failure']],
]);

it('selects the current trusted release PR with its tree', function () {
    $result = Process::env($this->candidateEnvironment)->run(['bash', base_path('.github/scripts/select-beta-candidate.sh')]);
    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(json_decode($result->output(), true))->toBe(json_decode(file_get_contents($this->candidateEnvironment['BETA_CANDIDATE_FILE']), true));
});

it('ignores an obsolete build before any Coolify mutation', function () {
    $candidate = json_decode(file_get_contents($this->candidateEnvironment['BETA_CANDIDATE_FILE']), true);
    $candidate['sha'] = str_repeat('a', 40);
    file_put_contents($this->candidateEnvironment['BETA_CANDIDATE_FILE'], json_encode($candidate));
    $result = Process::env($this->candidateEnvironment)->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($result->failed())->toBeTrue()
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse();
});

it('never targets the production application', function () {
    $result = Process::env(array_merge($this->candidateEnvironment, ['COOLIFY_BETA_APPLICATION_UUID' => 'production-test']))
        ->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($result->failed())->toBeTrue()
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse();
});

it('pins the current beta image and commit before requesting its deployment', function () {
    $result = Process::env($this->candidateEnvironment)->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $requests = array_map(fn ($line) => json_decode($line, true), file($this->candidateDirectory.'/requests.jsonl'));
    expect(array_column($requests, 'method'))->toBe(['PATCH', 'PATCH', 'POST'])
        ->and($requests[0]['path'])->toBe('/api/v1/applications/application-test/envs/bulk')
        ->and($requests[1]['body'])->toBe(['git_commit_sha' => str_repeat('b', 40)])
        ->and($requests[2]['body']['uuid'])->toBe('application-test');
});

it('separates candidate builds from trusted deployment scripts and never migrates automatically', function () {
    $workflow = file_get_contents(base_path('.github/workflows/beta.yml'));
    expect($workflow)->toContain('workflow_run:', 'workflow_dispatch:', 'workflows: [CI]', 'platforms: linux/amd64',
        'group: beta-deploy', 'cancel-in-progress: false', 'path: candidate', 'path: trusted', 'refs/heads/main')
        ->not->toContain('pull_request_target:', 'artisan migrate', 'artisan up', 'secrets.COOLIFY_TOKEN');
    $deploy = substr($workflow, strpos($workflow, "\n  deploy:"));
    expect($deploy)->toContain('secrets.COOLIFY_BETA_TOKEN', 'trusted/.github/scripts/deploy-beta.sh')
        ->not->toContain('path: candidate', 'packages: write');
});

it('deploys over a dedicated SSH key without a Coolify API credential', function () {
    $ssh = $this->candidateDirectory.'/ssh';
    file_put_contents($ssh, <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$@" > "$SSH_LOG"
cat > "$SSH_PAYLOAD"
# Check the client created private key files, without copying their contents.
while (($#)); do
    if [[ "$1" == -i ]]; then
        "$TEST_PHP_BINARY" -r 'exit((fileperms($argv[1]) & 0777) === 0600 ? 0 : 1);' "$2"
    fi
    shift
done
printf '{"deployment_uuid":"beta-deployment-test"}\n'
SH);
    chmod($ssh, 0700);
    $result = Process::env(array_merge($this->candidateEnvironment, [
        'PATH' => $this->candidateDirectory.':'.getenv('PATH'),
        'COOLIFY_TOKEN' => '', 'BETA_SSH_HOST' => '192.0.2.1',
        'BETA_SSH_PRIVATE_KEY' => 'fixture-private-key',
        'BETA_SSH_KNOWN_HOSTS' => '192.0.2.1 ssh-ed25519 fixture-public-host-key',
        'SSH_LOG' => $this->candidateDirectory.'/ssh.log',
        'SSH_PAYLOAD' => $this->candidateDirectory.'/ssh.json',
        'TEST_PHP_BINARY' => PHP_BINARY,
    ]))->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse()
        ->and(json_decode(file_get_contents($this->candidateDirectory.'/ssh.json'), true))->toBe([
            'application_uuid' => 'application-test',
            'sha' => str_repeat('b', 40),
            'image' => 'ghcr.io/zdossantos/dlp-friends@sha256:'.str_repeat('a', 64),
        ]);
    $references = array_map(fn ($line) => json_decode($line, true), file($this->candidateDirectory.'/scenario.json.refs'));
    expect($references[0]['body'])->toBe(['ref' => 'refs/heads/chore/beta-candidates/'.str_repeat('b', 40), 'sha' => str_repeat('b', 40)])
        ->and(array_column($references, 'method'))->toBe(['POST', 'GET']);
    $arguments = file_get_contents($this->candidateDirectory.'/ssh.log');
    expect($arguments)->toContain('StrictHostKeyChecking=yes', 'IdentitiesOnly=yes', 'BatchMode=yes', 'dlp-beta-deploy@192.0.2.1')
        ->not->toContain('fixture-private-key', 'fixture-token');
    expect($result->output().$result->errorOutput())->not->toContain('fixture-private-key');
});

it('reuses only an existing candidate branch that still points to the validated SHA', function (string $referenceSha, bool $allowed) {
    file_put_contents($this->candidateDirectory.'/scenario.json', json_encode(['existing_ref' => true, 'ref_sha' => $referenceSha]));
    file_put_contents($this->candidateDirectory.'/ssh', "#!/bin/sh\ntouch \"\$SSH_LOG\"\ncat >/dev/null\nprintf '{\"deployment_uuid\":\"beta-test\"}\\n'\n");
    chmod($this->candidateDirectory.'/ssh', 0700);
    $result = Process::env(array_merge($this->candidateEnvironment, [
        'PATH' => $this->candidateDirectory.':'.getenv('PATH'),
        'COOLIFY_TOKEN' => '', 'BETA_SSH_HOST' => '192.0.2.1',
        'BETA_SSH_PRIVATE_KEY' => 'fixture-private-key', 'BETA_SSH_KNOWN_HOSTS' => 'fixture-host-key',
        'SSH_LOG' => $this->candidateDirectory.'/ssh.log',
    ]))->run(['bash', base_path('.github/scripts/deploy-beta.sh')]);
    expect($result->successful())->toBe($allowed)
        ->and(file_exists($this->candidateDirectory.'/ssh.log'))->toBe($allowed)
        ->and(file_exists($this->candidateDirectory.'/requests.jsonl'))->toBeFalse();
})->with([
    'same SHA' => [str_repeat('b', 40), true],
    'moved reference' => [str_repeat('a', 40), false],
]);
