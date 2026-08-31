<?php

use App\Models\User;

function semanticHueIsBetweenScript(string $token, int $minimumHue, int $maximumHue): string
{
    return <<<JS
        (() => {
            const swatch = document.createElement('span');
            swatch.style.backgroundColor = 'var(--{$token})';
            document.body.append(swatch);
            const [red, green, blue] = getComputedStyle(swatch)
                .backgroundColor
                .match(/\d+(?:\.\d+)?/g)
                .slice(0, 3)
                .map((channel) => Number(channel) / 255);
            swatch.remove();

            const maximum = Math.max(red, green, blue);
            const minimum = Math.min(red, green, blue);
            const delta = maximum - minimum;

            if (delta === 0) {
                return 0;
            }

            const hue = maximum === red
                ? 60 * (((green - blue) / delta) % 6)
                : maximum === green
                    ? 60 * ((blue - red) / delta + 2)
                    : 60 * ((red - green) / delta + 4);

            const normalizedHue = hue < 0 ? hue + 360 : hue;

            return normalizedHue >= {$minimumHue} && normalizedHue <= {$maximumHue};
        })()
    JS;
}

function sharedPaletteHasPinkScript(): string
{
    return <<<'JS'
        (() => {
            const tokens = [
                '--primary',
                '--ring',
                '--secondary',
                '--accent',
                '--chart-1',
                '--chart-2',
                '--chart-3',
                '--chart-4',
                '--chart-5',
                '--sidebar-accent',
            ];
            return tokens.some((token) => {
                const swatch = document.createElement('span');
                swatch.style.backgroundColor = `var(${token})`;
                document.body.append(swatch);
                const [red, green, blue] = getComputedStyle(swatch)
                    .backgroundColor
                    .match(/\d+(?:\.\d+)?/g)
                    .slice(0, 3)
                    .map((channel) => Number(channel) / 255);
                swatch.remove();

                const maximum = Math.max(red, green, blue);
                const minimum = Math.min(red, green, blue);
                const delta = maximum - minimum;

                if (delta === 0) {
                    return false;
                }

                const calculatedHue = maximum === red
                    ? 60 * (((green - blue) / delta) % 6)
                    : maximum === green
                        ? 60 * ((blue - red) / delta + 2)
                        : 60 * ((red - green) / delta + 4);
                const hue = calculatedHue < 0
                    ? calculatedHue + 360
                    : calculatedHue;

                return hue >= 300;
            });
        })()
    JS;
}

function semanticContrastScript(string $backgroundToken, string $foregroundToken): string
{
    return <<<JS
        (() => {
            const resolveColor = (token) => {
                const swatch = document.createElement('span');
                swatch.style.color = 'var(' + token + ')';
                document.body.append(swatch);
                const channels = getComputedStyle(swatch)
                    .color
                    .match(/\d+(?:\.\d+)?/g)
                    .slice(0, 3)
                    .map(Number);
                swatch.remove();

                return channels;
            };
            const luminance = (channels) => channels
                .map((channel) => channel / 255)
                .map((channel) => channel <= 0.04045
                    ? channel / 12.92
                    : Math.pow((channel + 0.055) / 1.055, 2.4))
                .reduce((value, channel, index) =>
                    value + channel * [0.2126, 0.7152, 0.0722][index], 0);
            const background = luminance(resolveColor('--{$backgroundToken}'));
            const foreground = luminance(resolveColor('--{$foregroundToken}'));
            const lighter = Math.max(background, foreground);
            const darker = Math.min(background, foreground);

            return (lighter + 0.05) / (darker + 0.05) >= 4.5;
        })()
    JS;
}

function semanticColorEqualsRgbScript(string $token, int $red, int $green, int $blue): string
{
    return <<<JS
        (() => {
            const swatch = document.createElement('span');
            swatch.style.backgroundColor = 'var(--{$token})';
            document.body.append(swatch);
            const channels = getComputedStyle(swatch)
                .backgroundColor
                .match(/\d+(?:\.\d+)?/g)
                .slice(0, 3)
                .map(Number);
            swatch.remove();

            return channels[0] === {$red}
                && channels[1] === {$green}
                && channels[2] === {$blue};
        })()
    JS;
}

test('a stored appearance takes precedence over the system preference', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode();
    $page->script("localStorage.setItem('appearance', 'light')");
    $page->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", 'light')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertNoJavaScriptErrors();
});

test('the system appearance is used when no preference is stored', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode();
    $page->script("localStorage.removeItem('appearance')");
    $page->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", null)
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

test('appearance remains stable across repeated Inertia navigation', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance')->inDarkMode()
        ->click('Sombre')
        ->assertScript("localStorage.getItem('appearance')", 'dark')
        ->assertScript("document.documentElement.classList.contains('dark')", true);

    foreach (['/settings/account', '/settings/appearance', '/settings/account', '/settings/appearance'] as $url) {
        $page->navigate($url)
            ->assertScript("localStorage.getItem('appearance')", 'dark')
            ->assertScript("document.documentElement.classList.contains('dark')", true);
    }

    $page->click('Système')
        ->assertScript("localStorage.getItem('appearance')", 'system')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->navigate('/settings/account')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->navigate('/settings/appearance')
        ->assertScript("localStorage.getItem('appearance')", 'system')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

test('semantic colors keep turquoise secondary and neutral interaction accents in light and dark themes', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    $page = visit('/settings/appearance');
    $page->script("localStorage.setItem('appearance', 'light')");
    $page->navigate('/settings/appearance')
        ->assertScript(semanticHueIsBetweenScript('primary', 245, 290), true)
        ->assertScript(semanticHueIsBetweenScript('ring', 245, 290), true)
        ->assertScript(semanticColorEqualsRgbScript('secondary', 125, 211, 199), true)
        ->assertScript(semanticHueIsBetweenScript('secondary', 165, 185), true)
        ->assertScript(semanticHueIsBetweenScript('accent', 245, 275), true)
        ->assertScript(semanticHueIsBetweenScript('sidebar-accent', 245, 275), true)
        ->assertScript(semanticContrastScript('secondary', 'secondary-foreground'), true)
        ->assertScript(semanticContrastScript('accent', 'accent-foreground'), true)
        ->assertScript(sharedPaletteHasPinkScript(), false);

    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->navigate('/settings/appearance')
        ->assertScript(semanticHueIsBetweenScript('primary', 245, 290), true)
        ->assertScript(semanticHueIsBetweenScript('ring', 245, 290), true)
        ->assertScript(semanticColorEqualsRgbScript('secondary', 103, 199, 193), true)
        ->assertScript(semanticHueIsBetweenScript('secondary', 165, 185), true)
        ->assertScript(semanticHueIsBetweenScript('accent', 245, 275), true)
        ->assertScript(semanticHueIsBetweenScript('sidebar-accent', 245, 275), true)
        ->assertScript(semanticContrastScript('secondary', 'secondary-foreground'), true)
        ->assertScript(semanticContrastScript('accent', 'accent-foreground'), true)
        ->assertScript(sharedPaletteHasPinkScript(), false)
        ->assertNoJavaScriptErrors();
});
