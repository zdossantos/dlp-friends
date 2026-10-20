<?php

it('declares local brand icons with their formats and sizes', function () {
    $document = new DOMDocument;
    @$document->loadHTML('<html><head>'.view('components.brand-head')->render().'</head></html>');
    $xpath = new DOMXPath($document);
    foreach ([
        ['/favicon.svg', 'icon', 'image/svg+xml', 'any'],
        ['/favicon.ico', 'icon', 'image/x-icon', '16x16 32x32 48x48'],
        ['/apple-touch-icon.png', 'apple-touch-icon', 'image/png', '180x180'],
    ] as [$href, $rel, $type, $size]) {
        $node = $xpath->query('//link[@href="'.$href.'"]')->item(0);
        expect($node)->not->toBeNull()
            ->and($node->getAttribute('rel'))->toBe($rel)
            ->and($node->getAttribute('type'))->toBe($type)
            ->and($node->getAttribute('sizes'))->toBe($size);
    }
    foreach (['light' => 'hsl(32 40% 98%)', 'dark' => 'hsl(258 30% 8%)'] as $theme => $color) {
        expect($xpath->evaluate('string(//meta[@name="theme-color"][@media="(prefers-color-scheme: '.$theme.')"]/@content)'))->toBe($color);
    }
});

it('ships every approved variant with complete production and beta icon formats', function (string $variant) {
    foreach (['', 'beta-'] as $prefix) {
        $directory = public_path('brand/variants/'.$variant.'/');
        $favicon = new DOMDocument;
        expect($favicon->load($directory.$prefix.'favicon.svg'))->toBeTrue()
            ->and($favicon->documentElement->getAttribute('viewBox'))->toBe('0 0 256 256');
        $ico = file_get_contents($directory.$prefix.'favicon.ico');
        expect(unpack('vreserved/vtype/vcount', substr($ico, 0, 6)))->toBe(['reserved' => 0, 'type' => 1, 'count' => 3]);
        foreach ([16, 32, 48] as $index => $size) {
            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vsize/Voffset', substr($ico, 6 + $index * 16, 16));
            expect(getimagesizefromstring(substr($ico, $entry['offset'], $entry['size'])))
                ->toMatchArray([0 => $size, 1 => $size, 2 => IMAGETYPE_PNG]);
        }
        foreach (['apple-touch-icon' => 180, 'icon-192' => 192, 'icon-512' => 512, 'icon-maskable-192' => 192, 'icon-maskable-512' => 512] as $name => $size) {
            $path = $directory.$prefix.$name.'.png';
            expect(getimagesize($path))->toMatchArray([0 => $size, 1 => $size, 2 => IMAGETYPE_PNG]);
            $image = imagecreatefrompng($path);
            expect(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(0);
        }
    }
})->with(['standard-clair', 'standard-sombre', 'halloween-clair', 'halloween-sombre', 'noel-clair', 'noel-sombre']);

it('uses the approved dark composition for installed production and beta icons', function (string $prefix, string $variantPrefix) {
    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'pwa/icon-192.png', 'pwa/icon-512.png', 'pwa/icon-maskable-192.png', 'pwa/icon-maskable-512.png'] as $file) {
        expect(hash_file('sha256', public_path($prefix.$file)))
            ->toBe(hash_file('sha256', public_path('brand/variants/standard-sombre/'.$variantPrefix.basename($file))));
    }
})->with([['', ''], ['beta/', 'beta-']]);
