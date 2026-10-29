<?php

use Illuminate\Support\Facades\Process;

it('stops only dedicated beta containers through the SSH relay', function (string $scenario) {
    $result = Process::run(['python3', base_path('tests/Support/operations/stop_scenario.py'), $scenario]);
    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
})->with(['running', 'cold', 'missing-config', 'ownership-refused', 'wrong-database-owner', 'public-mailpit', 'stop-failure']);
