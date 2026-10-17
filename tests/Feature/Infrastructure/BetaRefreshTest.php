<?php

use Illuminate\Support\Facades\Process;

it('fails closed while protecting source resources during beta refresh', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/refresh_scenario.py'), $scenario]);

    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
})->with([
    'mysql-alias', 'resource-alias', 'volume-alias', 'bucket-alias', 'source-bucket-write',
    'missing-confirmation', 'active-process', 'schema-fingerprint', 'incomplete-import',
    'object-disappeared', 'private-error', 'interrupted-copy', 'complete-copy',
    'escaped-database-grants', 'wildcard-database-grants',
    'runtime-mysql-alias', 'rclone-key-alias', 'runtime-redis-alias',
]);
