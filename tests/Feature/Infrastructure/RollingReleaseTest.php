<?php

use Illuminate\Support\Facades\Process;

it('delivers compatible releases in order and blocks unsafe retries', function () {
    $result = Process::run(['python3', base_path('tests/Support/operations/rolling_release_test.py')]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

it('only deploys a verified release from the selected production branch', function () {
    $workflow = file_get_contents(base_path('.github/workflows/release-please.yml'));

    expect($workflow)->toContain(
        'record-release-image.result == \'success\'',
        'github.ref_name == vars.PRODUCTION_RELEASE_BRANCH',
        'environment: production',
        'group: production-delivery',
        'production-ssh-request.sh',
        'secrets.PRODUCTION_SSH_PRIVATE_KEY',
    )->not->toContain('secrets.COOLIFY_TOKEN');
});
