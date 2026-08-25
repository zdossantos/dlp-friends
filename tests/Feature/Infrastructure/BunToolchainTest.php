<?php

it('uses Bun as its only JavaScript package manager', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $composer = file_get_contents(base_path('composer.json'));

    expect($package)->toHaveKey('packageManager', 'bun@1.3.14')
        ->and($package['scripts'])->not->toHaveKey('test')
        ->and($package['scripts'])->not->toHaveKey('test:unit')
        ->and(base_path('bun.lock'))->toBeFile()
        ->and(base_path('package-lock.json'))->not->toBeFile()
        ->and(base_path('.npmrc'))->not->toBeFile()
        ->and(base_path('pnpm-workspace.yaml'))->not->toBeFile()
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

it('documents Bun without npm or Yarn residue in active project files', function () {
    $activeDocumentation = collect([
        'README.md',
        'AGENTS.md',
        'CONTRIBUTING.md',
        'docs/technical-architecture.md',
        'docs/quality-ci-cd.md',
    ])->map(fn (string $path): string => file_get_contents(base_path($path)))->join("\n");
    $ignoreFiles = file_get_contents(base_path('.gitignore')).file_get_contents(base_path('.dockerignore'));

    expect($activeDocumentation)->toContain('Bun 1.3.14')
        ->and($activeDocumentation)->not->toMatch('/\bnpm (ci|install|run|test)\b/')
        ->and($activeDocumentation)->not->toContain('package-lock.json')
        ->and($ignoreFiles)->not->toContain('npm-debug.log')
        ->and($ignoreFiles)->not->toContain('yarn-error.log');
});
