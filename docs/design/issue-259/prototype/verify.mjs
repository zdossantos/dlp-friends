import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const base =
    process.env.PROTOTYPE_URL ||
    'http://127.0.0.1:8259/docs/design/issue-259/prototype/index.html';
const captureDir = fileURLToPath(new URL('../captures/', import.meta.url));
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 320, height: 900 } });
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
await page.goto(base);
const registry = await page.evaluate(() => window.screens);
const results = {
    surfaces: registry.length,
    renders: 0,
    interactions: [],
    contrast: [],
};
assert.equal(new Set(registry.map((s) => s.id)).size, registry.length);

async function visit(id, query = '') {
    await page.goto(`${base}?screen=${id}${query}`);
    await page.evaluate(() => document.fonts.ready);
    const bounds = await page.evaluate(() => {
        const d = document.querySelector('dialog[open]');

        return {
            width: innerWidth,
            document: document.documentElement.scrollWidth,
            panel: d?.scrollWidth || 0,
            panelWidth: d?.clientWidth || 0,
        };
    });
    assert.ok(
        bounds.document <= bounds.width + 1,
        `${id}${query}: document overflow`,
    );
    assert.ok(
        bounds.panel <= bounds.panelWidth + 1,
        `${id}${query}: panel overflow`,
    );
    results.renders++;
}

for (const width of [320, 1440]) {
    await page.setViewportSize({ width, height: 900 });

    for (const lang of ['fr', 'en']) {
        for (const screen of registry) {
            await visit(screen.id, `&lang=${lang}`);
        }

        for (const theme of ['light', 'dark']) {
            for (const season of ['standard', 'halloween', 'christmas']) {
                for (const state of [
                    'normal',
                    'empty',
                    'loading',
                    'error',
                    'success',
                    'disabled',
                    'readonly',
                    'selected',
                    'unread',
                ]) {
                    await visit(
                        'components',
                        `&lang=${lang}&theme=${theme}&season=${season}&state=${state}`,
                    );
                }
            }
        }
    }
}

await page.setViewportSize({ width: 320, height: 900 });
await visit('register');
assert.equal(await page.locator('[data-consent]').isDisabled(), true);
await page.locator('#accept-terms').check();
assert.equal(await page.locator('[data-consent]').isEnabled(), true);
results.interactions.push('consent');
await visit('tutorial-pass');
await page.locator('[data-action="tutorial-pass"]').click();
await page.locator('[data-action="tutorial-like"]').click();
await page.locator('[data-screen="tutorial-chat"]').click();
await page.locator('#message').fill('Un premier message fictif.');
await page.locator('#send-message').click();
assert.match(page.url(), /screen=install/);
results.interactions.push('onboarding');
await visit('chat', '&state=empty');
await page.locator('[data-action^="starter:"]').first().click();
assert.ok(await page.locator('#message').inputValue());
assert.equal(await page.locator('#send-message').isEnabled(), true);
await page.locator('#send-message').click();
assert.equal(await page.locator('.message.mine').count(), 1);
await visit('group-chat', '&state=readonly');
assert.equal(await page.locator('#message').isDisabled(), true);
results.interactions.push('starter, send, read-only');
await visit('event');
await page.locator('[data-action="event-accepted"]').click();
await page.locator('[data-action="participants"]').click();
await page.locator('#panel [data-action="open-profile"]').click();
assert.match(page.url(), /screen=participant-profile/);
await page.locator('#panel [data-action="participants"]').click();
assert.match(page.url(), /screen=participants/);
await page.goBack();
assert.match(page.url(), /screen=participant-profile/);
await page.keyboard.press('Escape');
assert.match(page.url(), /screen=participants/);
results.interactions.push('event panels, browser back, Escape');
await visit('passed');
await page.locator('[data-action="open-profile"]').first().click();
assert.match(page.url(), /screen=passed-profile/);
assert.equal(await page.locator('dialog[open]').count(), 1);
await page.keyboard.press('Escape');
assert.match(page.url(), /screen=passed/);
results.interactions.push('passed profile');
await visit('members');
await page.locator('.cards-mobile [data-action="ban"]').first().click();
assert.equal(await page.locator('#ban-reason').count(), 1);
await page.keyboard.press('Escape');
assert.equal(await page.locator('dialog[open]').count(), 0);
results.interactions.push('destructive confirmation, focus return');
await visit('notifications');
await page.locator('[data-action="read-all"]').click();
assert.equal(await page.locator('.list-item').count(), 0);
results.interactions.push('mark read');
await page.emulateMedia({ reducedMotion: 'reduce' });
assert.equal(
    await page.evaluate(
        () =>
            getComputedStyle(document.querySelector('.btn')).transitionDuration,
    ),
    '0s',
);
results.interactions.push('reduced motion');
await page.emulateMedia({ reducedMotion: 'no-preference' });

function luminance(rgb) {
    const c = rgb
        .map((n) => n / 255)
        .map((n) => (n <= 0.04045 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4));

    return c[0] * 0.2126 + c[1] * 0.7152 + c[2] * 0.0722;
}

for (const theme of ['light', 'dark']) {
    for (const season of ['standard', 'halloween', 'christmas']) {
        await visit('components', `&theme=${theme}&season=${season}`);
        const pairs = await page.evaluate(() => {
            const root = document.documentElement;
            const values = [
                'foreground',
                'background',
                'card',
                'muted-foreground',
                'primary',
                'on-primary',
                'secondary',
                'on-secondary',
            ].map((token) => {
                const e = document.createElement('span');
                e.style.color = `var(--${token})`;
                root.append(e);
                const rgb = getComputedStyle(e)
                    .color.match(/[\d.]+/g)
                    .slice(0, 3)
                    .map(Number);
                e.remove();

                return [token, rgb];
            });

            return Object.fromEntries(values);
        });

        for (const [fg, bg] of [
            ['foreground', 'background'],
            ['foreground', 'card'],
            ['muted-foreground', 'card'],
            ['on-primary', 'primary'],
            ['on-secondary', 'secondary'],
        ]) {
            const a = luminance(pairs[fg]);
            const b = luminance(pairs[bg]);
            const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
            assert.ok(ratio >= 4.5, `${theme}/${season}/${fg}/${bg}: ${ratio}`);
            results.contrast.push({
                theme,
                season,
                pair: `${fg}/${bg}`,
                ratio: Number(ratio.toFixed(2)),
            });
        }
    }
}

await mkdir(captureDir, { recursive: true });

for (const [id, width, theme, season] of [
    ['explore', 390, 'light', 'standard'],
    ['home', 1440, 'light', 'standard'],
    ['members', 320, 'light', 'standard'],
    ['partner-profile', 390, 'dark', 'standard'],
    ['group-chat', 390, 'dark', 'halloween'],
    ['events', 1440, 'dark', 'christmas'],
]) {
    await page.setViewportSize({ width, height: 900 });
    await visit(id, `&theme=${theme}&season=${season}`);
    await page.screenshot({
        path: `${captureDir}/${id}-${width}.png`,
        fullPage: true,
    });
}

assert.deepEqual(errors, []);
await writeFile(
    `${captureDir}/checks.json`,
    JSON.stringify(results, null, 2) + '\n',
);
console.log(
    JSON.stringify(
        {
            surfaces: results.surfaces,
            renders: results.renders,
            interactions: results.interactions,
            contrastPairs: results.contrast.length,
            errors,
        },
        null,
        2,
    ),
);
await browser.close();
