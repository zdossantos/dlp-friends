<?php

use App\Http\Middleware\EnsureDeploymentReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('selects beta branding at runtime in both languages', function (string $locale, string $name) {
    app()->instance('env', 'beta');
    app()->setLocale($locale);
    $html = view('components.brand-head')->render();

    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'app.webmanifest'] as $asset) {
        expect($html)->toContain('href="/beta/'.$asset.'"');
    }
    expect($html)->toContain('content="'.$name.'"');

    $this->withoutMiddleware(EnsureDeploymentReady::class)->withHeader('Accept-Language', $locale)->get('/login')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('environment.isBeta', true)
        ->where('i18n.messages.brand.beta_app_name', $name));
})->with([['fr', 'DLP Friends Bêta'], ['en', 'DLP Friends Beta']]);

it('keeps production branding unchanged', function () {
    app()->instance('env', 'production');
    expect(view('components.brand-head')->render())->not->toContain('/beta/');
    $this->withoutMiddleware(EnsureDeploymentReady::class)->get('/login')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('environment.isBeta', false));
});

it('ships distinct beta icons including a badge inside the maskable safe circle', function () {
    $manifest = json_decode(file_get_contents(public_path('beta/app.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest)->toMatchArray(['id' => '/beta', 'start_url' => '/app', 'scope' => '/'])
        ->and($manifest['name'])->toContain('Bêta')->and($manifest['icons'])->toHaveCount(4);

    foreach ($manifest['icons'] as $icon) {
        [$size] = explode('x', $icon['sizes']);
        $path = public_path(ltrim($icon['src'], '/'));
        expect($icon['src'])->toStartWith('/beta/pwa/');
        expect(getimagesize($path))->toMatchArray([0 => (int) $size, 1 => (int) $size, 2 => IMAGETYPE_PNG]);
        expect(hash_file('sha256', $path))->not->toBe(hash_file('sha256', public_path(substr($icon['src'], 6))));
        if ($icon['purpose'] === 'maskable') {
            $image = imagecreatefrompng($path);
            // Every white badge pixel must survive the standard circular 80% mask.
            for ($y = 0; $y < (int) $size; $y++) {
                for ($x = 0; $x < (int) $size; $x++) {
                    $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if ($rgb['red'] > 245 && $rgb['green'] > 245 && $rgb['blue'] > 245) {
                        expect(hypot($x - (int) $size / 2, $y - (int) $size / 2))->toBeLessThan((int) $size * .4);
                    }
                }
            }
        }
    }
    expect(getimagesize(public_path('beta/apple-touch-icon.png')))->toMatchArray([0 => 180, 1 => 180]);
    $ico = file_get_contents(public_path('beta/favicon.ico'));
    expect(unpack('vreserved/vtype/vcount', substr($ico, 0, 6)))->toBe(['reserved' => 0, 'type' => 1, 'count' => 3]);
    expect(file_get_contents(public_path('beta/favicon.svg')))->toContain('BETA');
});
