<?php

use Illuminate\Support\Facades\Process;

it('verifies the beta lifecycle boundaries without modifying real resources', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/lifecycle_scenario.py'), $scenario]);
    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
})->with(['update', 'stop', 'cold-stop', 'stop-without-web', 'partial-stop', 'reopening-failure', 'production-copy', 'references-copy', 'no-confirmation', 'failed-initialization', 'migration-failure', 'deploy-failure']);

it('exports only allowlisted reference data and validates object paths', function () {
    $result = Process::run(['python3', base_path('tests/Support/operations/reference_scenario.py')]);
    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
});

it('checks actual resource ownership before lifecycle mutations', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/resource_scenario.py'), $scenario]);
    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
})->with(['valid', 'valid-stopped', 'legacy-labels', 'resource-alias', 'container-alias', 'volume-alias', 'missing-volume-alias']);

it('checks the effective maintenance namespace and verifies reopening', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/maintenance_scenario.py'), $scenario]);
    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
})->with(['valid', 'wrong-prefix', 'wrong-connection', 'reopening-failure', 'down-not-persisted']);
