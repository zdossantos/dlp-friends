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

it('uses the pinned Bun toolchain in automation and Docker', function () {
    $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $dependabot = file_get_contents(base_path('.github/dependabot.yml'));

    expect($ci)->toContain('oven-sh/setup-bun@0c5077e51419868618aeaa5fe8019c62421857d6')
        ->and($ci)->toContain("bun-version: '1.3.14'")
        ->and($ci)->toContain('bun install --frozen-lockfile')
        ->and($ci)->not->toContain('actions/setup-node')
        ->and($ci)->not->toContain('npm ci')
        ->and($dockerfile)->toContain('FROM oven/bun:1.3.14-alpine AS bun')
        ->and($dockerfile)->toContain('bun install --frozen-lockfile')
        ->and($dockerfile)->not->toContain('npm ci')
        ->and($dependabot)->toContain("package-ecosystem: 'bun'")
        ->and($dependabot)->not->toContain("package-ecosystem: 'npm'");
});
