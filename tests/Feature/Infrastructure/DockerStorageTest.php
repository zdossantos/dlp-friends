<?php

use Symfony\Component\Process\Process;

it('shares persistent application storage between Docker services', function () {
    $process = new Process([
        'docker',
        'compose',
        '--project-directory',
        base_path(),
        'config',
        '--format',
        'json',
    ], base_path());

    $process->mustRun();

    $configuration = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $expectedMount = [
        'type' => 'volume',
        'source' => 'app-storage',
        'target' => '/var/www/html/storage/app',
    ];

    foreach (['web', 'worker', 'scheduler', 'reverb'] as $service) {
        $mounts = collect($configuration['services'][$service]['volumes'] ?? [])
            ->map(fn (array $mount): array => array_intersect_key($mount, $expectedMount))
            ->all();

        expect($mounts)
            ->toContain($expectedMount);
    }

    expect($configuration['volumes'])
        ->toHaveKey('app-storage');
});
