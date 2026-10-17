<?php

use Illuminate\Support\Facades\Process;

it('enforces the production release gate at external boundaries', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/release_scenario.py'), $scenario]);

    expect($result->successful())->toBeTrue($result->errorOutput());
})->with([
    'missing-report', 'failed-report', 'expired-snapshot', 'future-snapshot',
    'different-tree', 'different-migrations', 'changed-schema', 'mutable-image',
    'wrong-platform', 'active-process', 'maintenance-absent', 'unverified-backup',
    'squashed-equivalent-tree', 'migration-failed', 'readiness-failed',
    'coolify-timeout', 'resume-without-verification', 'resume-success',
    'rehearse-success', 'missing-journeys', 'deploy-success', 'old-background-image',
    'runtime-valid', 'runtime-wrong-prefix', 'runtime-wrong-database', 'runtime-wrong-server',
    'dirty-beta-schema', 'dirty-beta-migrations', 'http-drain',
]);

it('publishes release metadata without deploying production', function () {
    $workflow = file_get_contents(base_path('.github/workflows/release-please.yml'));

    expect($workflow)->not->toContain('secrets.COOLIFY_TOKEN', 'deploy-release.sh', 'Deploy release to Coolify')
        ->toContain('container-image.json', 'io.dlp-friends.source-tree', 'io.dlp-friends.migrations-sha256');
});
