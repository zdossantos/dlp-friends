<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_application_declares_local_brand_icons_with_their_formats_and_sizes(): void
    {
        $response = $this->get('/login')->assertOk();
        $document = new DOMDocument;

        @$document->loadHTML($response->getContent());
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
    }

    public function test_svg_favicon_uses_the_black_brand_mark_without_a_background(): void
    {
        $favicon = $this->loadSvg(public_path('favicon.svg'));
        $xpath = new DOMXPath($favicon);
        $paths = $xpath->query('/*[local-name()="svg"]/*[local-name()="path"]');
        $backgrounds = $xpath->query('/*[local-name()="svg"]/*[local-name()="rect"]');

        $this->assertSame('512', $favicon->documentElement->getAttribute('width'));
        $this->assertSame('419', $favicon->documentElement->getAttribute('height'));
        $this->assertSame('0 0 512 419', $favicon->documentElement->getAttribute('viewBox'));
        $this->assertCount(11, $paths);
        $this->assertCount(0, $backgrounds);

        foreach ($paths as $path) {
            $fill = $path->attributes?->getNamedItem('fill')?->nodeValue;
            $stroke = $path->attributes?->getNamedItem('stroke')?->nodeValue;

            $this->assertContains('black', [$fill, $stroke]);
        }
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
        $this->assertSame(127, $corner['alpha']);
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
}
