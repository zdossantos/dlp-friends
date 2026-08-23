<?php

it('uses Bun as its only JavaScript package manager', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $composer = file_get_contents(base_path('composer.json'));

    expect($package)->toHaveKey('packageManager', 'bun@1.3.14')
        ->and($package['scripts'])->toHaveKey('test', 'bun run test:unit')
        ->and(base_path('bun.lock'))->toBeFile()
        ->and(base_path('package-lock.json'))->not->toBeFile()
        ->and(base_path('.npmrc'))->not->toBeFile()
        ->and($composer)->toContain('bun install')
        ->and($composer)->toContain('bun run build')
        ->and($composer)->not->toContain('npm install')
        ->and($composer)->not->toContain('npm run');
});
