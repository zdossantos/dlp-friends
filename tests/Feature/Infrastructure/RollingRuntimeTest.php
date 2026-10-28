<?php

use Illuminate\Support\Facades\Process;

it('finishes accepted HTTP writes and PHP shutdown callbacks before stopping', function () {
    $result = Process::timeout(120)->run([
        'python3', base_path('tests/Support/operations/rolling_runtime.py'), getenv('DLP_RUNTIME_TEST_IMAGE'),
    ]);

    expect($result->successful())->toBeTrue($result->errorOutput());
})->skip(fn () => ! getenv('DLP_RUNTIME_TEST_IMAGE'), 'Run with the built Docker runtime image.');

it('serves retained and current chunks on both overlapping web containers', function () {
    $result = Process::timeout(120)->run([
        'python3', base_path('tests/Support/operations/rolling_assets.py'), getenv('DLP_RUNTIME_TEST_IMAGE'),
    ]);

    expect($result->successful())->toBeTrue($result->errorOutput());
})->skip(fn () => ! getenv('DLP_RUNTIME_TEST_IMAGE'), 'Run with the built Docker runtime image.');
