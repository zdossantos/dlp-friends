<?php

it('preserves the approved Horizon paths in all six transparent variants', function () {
    $source = new DOMDocument;
    $source->load(base_path('scripts/brand/horizon-source.svg'));
    $expected = [];
    foreach ($source->getElementsByTagName('path') as $path) {
        $expected[] = $path->getAttribute('d');
    }
    foreach (['', '-dark', '-halloween', '-halloween-dark', '-christmas', '-christmas-dark'] as $suffix) {
        $svg = new DOMDocument;
        expect($svg->load(public_path("brand/dlp-friends-logo{$suffix}.svg")))->toBeTrue();
        expect($svg->documentElement->getAttribute('viewBox'))->toBe('0 0 256 256');
        $paths = [];
        foreach ($svg->getElementsByTagName('path') as $path) {
            $paths[] = $path->getAttribute('d');
        }
        expect($paths)->toEqualCanonicalizing($expected)
            ->and($svg->getElementsByTagName('mask')->length)->toBe(1)
            ->and(file_get_contents(public_path("brand/dlp-friends-logo{$suffix}.svg")))->not->toContain('#FFFEFC');
    }
});

it('keeps the complete mark and beta badge inside the maskable safe circle', function (string $prefix) {
    foreach ([192, 512] as $size) {
        $image = imagecreatefrompng(public_path("{$prefix}icon-maskable-{$size}.png"));
        $background = imagecolorat($image, 0, 0);
        $maximumDistance = 0.0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (imagecolorat($image, $x, $y) !== $background) {
                    $maximumDistance = max($maximumDistance, hypot($x - $size / 2, $y - $size / 2));
                }
            }
        }
        expect($maximumDistance)->toBeLessThan($size * .4);
    }
})->with(['pwa/', 'beta/pwa/', ...array_merge(...array_map(
    fn (string $variant): array => [
        'brand/variants/'.$variant.'/',
        'brand/variants/'.$variant.'/beta-',
    ],
    ['standard-clair', 'standard-sombre', 'halloween-clair', 'halloween-sombre', 'noel-clair', 'noel-sombre'],
))]);

it('provides a transparent monochrome notification badge', function () {
    $path = public_path('pwa/badge-96.png');
    expect(getimagesize($path))->toMatchArray([0 => 96, 1 => 96, 2 => IMAGETYPE_PNG]);
    $image = imagecreatefrompng($path);
    expect(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(127);
});
