<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_application_declares_local_brand_icons_with_their_formats_and_sizes(): void
    {
        $document = new DOMDocument;

        @$document->loadHTML(sprintf(
            '<!doctype html><html><head>%s</head><body></body></html>',
            view('components.brand-head')->render(),
        ));
        $xpath = new DOMXPath($document);

        $this->assertLinkAttributes($xpath, '/favicon.svg', [
            'rel' => 'icon',
            'type' => 'image/svg+xml',
            'sizes' => 'any',
        ]);
        $this->assertLinkAttributes($xpath, '/favicon.ico', [
            'rel' => 'icon',
            'type' => 'image/x-icon',
            'sizes' => '16x16 32x32 48x48',
        ]);
        $this->assertLinkAttributes($xpath, '/apple-touch-icon.png', [
            'rel' => 'apple-touch-icon',
            'type' => 'image/png',
            'sizes' => '180x180',
        ]);

        $this->assertMetaAttributes($xpath, [
            'name' => 'theme-color',
            'media' => '(prefers-color-scheme: light)',
            'content' => 'hsl(32 40% 98%)',
        ]);
        $this->assertMetaAttributes($xpath, [
            'name' => 'theme-color',
            'media' => '(prefers-color-scheme: dark)',
            'content' => 'hsl(258 30% 8%)',
        ]);
    }

    public function test_colored_logo_variants_preserve_the_brand_mark_geometry(): void
    {
        $light = $this->loadSvg(public_path('brand/dlp-friends-logo.svg'));
        $dark = $this->loadSvg(public_path('brand/dlp-friends-logo-dark.svg'));

        $this->assertSame('0 0 512 418.648', $light->documentElement->getAttribute('viewBox'));
        $this->assertSame('0 0 512 418.648', $dark->documentElement->getAttribute('viewBox'));
        $this->assertSame(
            $this->pathData($light, '//*[local-name()="path"]'),
            $this->pathData($dark, '//*[local-name()="path"]'),
        );
        $this->assertSame(
            ['url(#brand-gradient)'],
            $this->paintColors($light),
        );
        $this->assertSame(
            ['url(#brand-gradient)'],
            $this->paintColors($dark),
        );
        $this->assertSame(
            ['hsl(263 63% 46%)', '#FCE6F1'],
            $this->gradientStops($light),
        );
        $this->assertSame(
            ['hsl(265 80% 72%)', '#F3C8DE'],
            $this->gradientStops($dark),
        );
    }

    public function test_svg_favicon_uses_the_colored_mark_on_the_rounded_dark_background(): void
    {
        $favicon = $this->loadSvg(public_path('favicon.svg'));
        $xpath = new DOMXPath($favicon);
        $paths = $xpath->query('//*[local-name()="path"]');
        $backgrounds = $xpath->query('//*[local-name()="rect"]');

        $this->assertSame('512', $favicon->documentElement->getAttribute('width'));
        $this->assertSame('512', $favicon->documentElement->getAttribute('height'));
        $this->assertSame('0 0 512 512', $favicon->documentElement->getAttribute('viewBox'));
        $this->assertCount(11, $paths);
        $this->assertCount(1, $backgrounds);
        $this->assertSame('hsl(258 30% 8%)', $backgrounds->item(0)?->attributes?->getNamedItem('fill')?->nodeValue);
        $this->assertSame('96', $backgrounds->item(0)?->attributes?->getNamedItem('rx')?->nodeValue);
        $this->assertSame('96', $backgrounds->item(0)?->attributes?->getNamedItem('ry')?->nodeValue);
        $this->assertSame(
            ['url(#brand-gradient)'],
            $this->paintColors($favicon),
        );
        $this->assertSame(
            ['hsl(265 80% 72%)', '#F3C8DE'],
            $this->gradientStops($favicon),
        );
    }

    public function test_apple_touch_icon_is_an_intact_180_pixel_png(): void
    {
        $path = public_path('apple-touch-icon.png');
        $image = getimagesize($path);

        $this->assertNotFalse($image);
        $this->assertSame([180, 180], [$image[0], $image[1]]);
        $this->assertSame(IMAGETYPE_PNG, $image[2]);
        $this->assertSame('image/png', mime_content_type($path));

        $png = imagecreatefrompng($path);

        $this->assertNotFalse($png);
        $corner = imagecolorsforindex($png, imagecolorat($png, 0, 0));
        $background = imagecolorsforindex($png, imagecolorat($png, 90, 10));

        $this->assertSame(127, $corner['alpha']);
        $this->assertSame(0, $background['alpha']);
        $this->assertSame([18, 14, 27], [$background['red'], $background['green'], $background['blue']]);
    }

    public function test_ico_contains_intact_png_variants_for_common_browser_sizes(): void
    {
        $contents = file_get_contents(public_path('favicon.ico'));

        $this->assertNotFalse($contents);
        $header = unpack('vreserved/vtype/vcount', substr($contents, 0, 6));
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $header);

        $dimensions = [];

        for ($index = 0; $index < $header['count']; $index++) {
            $entry = unpack(
                'Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vsize/Voffset',
                substr($contents, 6 + ($index * 16), 16),
            );
            $imageBytes = substr($contents, $entry['offset'], $entry['size']);
            $image = getimagesizefromstring($imageBytes);

            $this->assertNotFalse($image);
            $this->assertSame(IMAGETYPE_PNG, $image[2]);
            $this->assertSame([$entry['width'], $entry['height']], [$image[0], $image[1]]);
            $dimensions[] = [$image[0], $image[1]];
        }

        $this->assertSame([[16, 16], [32, 32], [48, 48]], $dimensions);
    }

    /** @param array<string, string> $attributes */
    private function assertLinkAttributes(DOMXPath $xpath, string $href, array $attributes): void
    {
        $nodes = $xpath->query(sprintf('//link[@href="%s"]', $href));

        $this->assertNotFalse($nodes);
        $this->assertCount(1, $nodes);

        foreach ($attributes as $name => $value) {
            $this->assertSame($value, $nodes->item(0)?->attributes?->getNamedItem($name)?->nodeValue);
        }
    }

    /** @param array<string, string> $attributes */
    private function assertMetaAttributes(DOMXPath $xpath, array $attributes): void
    {
        $query = '//meta';

        foreach ($attributes as $name => $value) {
            $query .= sprintf('[@%s="%s"]', $name, $value);
        }

        $nodes = $xpath->query($query);

        $this->assertNotFalse($nodes);
        $this->assertCount(1, $nodes);
    }

    private function loadSvg(string $path): DOMDocument
    {
        $document = new DOMDocument;

        $this->assertTrue($document->load($path));

        return $document;
    }

    /** @return list<string> */
    private function pathData(DOMDocument $document, string $query): array
    {
        $nodes = (new DOMXPath($document))->query($query);
        $paths = [];

        $this->assertNotFalse($nodes);

        foreach ($nodes as $node) {
            $paths[] = preg_replace('/\s+/', ' ', trim($node->attributes?->getNamedItem('d')?->nodeValue ?? ''));
        }

        return $paths;
    }

    /** @return list<string> */
    private function paintColors(DOMDocument $document): array
    {
        $colors = [];
        $nodes = (new DOMXPath($document))->query('//*[local-name()="path"]');

        $this->assertNotFalse($nodes);

        foreach ($nodes as $node) {
            foreach (['fill', 'stroke'] as $attribute) {
                $color = $node->attributes?->getNamedItem($attribute)?->nodeValue;

                if ($color !== null && ! in_array($color, $colors, true)) {
                    $colors[] = $color;
                }
            }
        }

        return $colors;
    }

    /** @return list<string> */
    private function gradientStops(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $gradients = $xpath->query('//*[local-name()="linearGradient" and @id="brand-gradient"]');
        $stops = $xpath->query('//*[local-name()="linearGradient" and @id="brand-gradient"]/*[local-name()="stop"]');

        $this->assertNotFalse($gradients);
        $this->assertCount(1, $gradients);
        $this->assertSame('userSpaceOnUse', $gradients->item(0)?->attributes?->getNamedItem('gradientUnits')?->nodeValue);
        $this->assertNotFalse($stops);
        $this->assertCount(2, $stops);

        return array_map(
            static fn (int $index): string => $stops->item($index)?->attributes?->getNamedItem('stop-color')?->nodeValue ?? '',
            [0, 1],
        );
    }
}
