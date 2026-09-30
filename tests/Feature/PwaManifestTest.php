<?php

it('publishes an installable manifest with safe brand assets', function () {
    $manifestPath = public_path('app.webmanifest');

    expect($manifestPath)->toBeFile();

    $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toMatchArray([
            'id' => '/',
            'scope' => '/',
            'start_url' => '/app',
            'display' => 'standalone',
        ])
        ->and($manifest['icons'])->toHaveCount(4)
        ->and(collect($manifest['icons'])->pluck('purpose')->all())
        ->toEqualCanonicalizing(['any', 'any', 'maskable', 'maskable'])
        ->and($manifest['screenshots'])->toHaveCount(2);

    foreach ([...$manifest['icons'], ...$manifest['screenshots']] as $asset) {
        expect(public_path(ltrim($asset['src'], '/')))->toBeFile();
    }

    expect($manifest['icons'])->toBe([
        ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/pwa/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => '/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ]);
});

it('links the pwa metadata once from application documents', function () {
    $response = $this->get('/fr');

    $response->assertOk();

    $html = $response->getContent();

    expect(substr_count($html, 'rel="manifest"'))->toBe(1)
        ->and(substr_count($html, 'apple-mobile-web-app-capable'))->toBe(1)
        ->and($html)->toContain('href="/app.webmanifest"')
        ->and($html)->toContain('content="yes"');
});
