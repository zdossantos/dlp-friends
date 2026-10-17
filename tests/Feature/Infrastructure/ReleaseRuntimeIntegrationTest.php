<?php

use Illuminate\Support\Facades\Process;

it('preserves the maintenance namespace and drains real HTTP writes', function () {
    $result = Process::timeout(180)->run([
        'python3', base_path('tests/Support/operations/runtime_integration.py'), getenv('DLP_RUNTIME_TEST_IMAGE'),
    ]);

    expect($result->successful())->toBeTrue($result->errorOutput());
})->skip(fn () => ! getenv('DLP_RUNTIME_TEST_IMAGE'), 'Run with the built Docker runtime image.');
