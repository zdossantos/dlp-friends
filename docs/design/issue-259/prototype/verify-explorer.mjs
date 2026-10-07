import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
const base = process.env.PROTOTYPE_URL || 'http://127.0.0.1:8260/index.html';
const output = new URL('../captures/revision-3/', import.meta.url);
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage();
const failures = [];
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
let renders = 0;

for (const viewport of [
    { width: 320, height: 480 },
    { width: 320, height: 568 },
    { width: 390, height: 600 },
    { width: 390, height: 844 },
    { width: 1440, height: 900 },
]) {
    await page.setViewportSize(viewport);

    for (const lang of ['fr', 'en']) {
        for (const season of ['standard', 'halloween', 'christmas']) {
            for (const theme of ['light', 'dark']) {
                for (const state of [
                    'normal',
                    'empty',
                    'loading',
                    'error',
                    'success',
                    'disabled',
                ]) {
                    await page.goto(
                        `${base}?screen=explore&lang=${lang}&season=${season}&theme=${theme}&state=${state}`,
                    );
                    await page.evaluate(() => document.fonts.ready);
                    await page
                        .locator('#app img')
                        .evaluateAll((images) =>
                            Promise.all(images.map((image) => image.decode())),
                        );
                    await page.evaluate(() =>
                        Promise.all(
                            document
                                .getAnimations()
                                .map((animation) => animation.finished),
                        ),
                    );
                    const result = await page.evaluate(() => {
                        const avatar = document.querySelector(
                            '.card-portrait .avatar',
                        );
                        const picture = avatar?.querySelector('img');
                        const avatarBounds = avatar?.getBoundingClientRect();
                        const imageBounds = picture?.getBoundingClientRect();
                        const avatarFits =
                            !avatar ||
                            (picture.naturalWidth > 0 &&
                                imageBounds.top >= avatarBounds.top - 1 &&
                                imageBounds.bottom <= avatarBounds.bottom + 1 &&
                                imageBounds.height >= 48);
                        const nav = document
                            .querySelector('.bottom-nav')
                            .getBoundingClientRect();
                        const controls = [
                            ...document.querySelectorAll('#app a, #app button'),
                        ].map((el) => ({
                            label: el.textContent.trim(),
                            ...Object.fromEntries(
                                [
                                    'top',
                                    'bottom',
                                    'left',
                                    'right',
                                    'width',
                                    'height',
                                ].map((k) => [
                                    k,
                                    el.getBoundingClientRect()[k],
                                ]),
                            ),
                        }));

                        return {
                            avatarFits,
                            height: innerHeight,
                            scrollHeight: document.documentElement.scrollHeight,
                            width: innerWidth,
                            scrollWidth: document.documentElement.scrollWidth,
                            controls,
                            navTop: nav.top,
                            mainBottom: document
                                .querySelector('#content')
                                .getBoundingClientRect().bottom,
                            contentControlBottom: Math.max(
                                ...[
                                    ...document.querySelectorAll(
                                        '#content a, #content button',
                                    ),
                                ].map(
                                    (el) => el.getBoundingClientRect().bottom,
                                ),
                            ),
                        };
                    });

                    if (
                        !result.avatarFits ||
                        result.contentControlBottom > result.mainBottom + 1 ||
                        result.scrollHeight > result.height + 1 ||
                        result.scrollWidth > result.width + 1 ||
                        result.controls.some(
                            (c) =>
                                c.height < 43.9 ||
                                c.width < 43.9 ||
                                c.bottom > result.height + 1 ||
                                c.top < 0 ||
                                c.left < 0 ||
                                c.right > result.width + 1,
                        )
                    ) {
                        failures.push({
                            viewport,
                            lang,
                            season,
                            theme,
                            state,
                            result,
                        });
                    }

                    renders++;
                }
            }
        }
    }
}

const shots = [
    ['standard', 'light', 320, 480, 'small-browser'],
    ['standard', 'light', 390, 600, 'standard-light'],
    ['standard', 'dark', 390, 844, 'standard-dark-pwa'],
    ['halloween', 'dark', 390, 600, 'halloween'],
    ['christmas', 'light', 390, 600, 'christmas'],
    ['standard', 'light', 1440, 900, 'desktop'],
];

for (const [season, theme, width, height, name] of shots) {
    await page.setViewportSize({ width, height });
    await page.goto(`${base}?screen=explore&season=${season}&theme=${theme}`);
    await page.evaluate(() => document.fonts.ready);
    await page
        .locator('#app img')
        .evaluateAll((images) =>
            Promise.all(images.map((image) => image.decode())),
        );
    await page.evaluate(() =>
        Promise.all(
            document.getAnimations().map((animation) => animation.finished),
        ),
    );
    await page.screenshot({ path: new URL(`${name}.png`, output).pathname });
}

await page.setViewportSize({ width: 320, height: 480 });
await page.goto(`${base}?screen=explore`);
await page.getByRole('button', { name: 'Découvrir', exact: true }).click();
assert.equal(await page.locator('#panel').evaluate((el) => el.open), true);
await page.keyboard.press('Escape');
assert.equal(await page.locator('#panel').evaluate((el) => el.open), false);
await page.emulateMedia({ reducedMotion: 'reduce' });
assert.equal(
    await page
        .locator('.decision-actions button')
        .first()
        .evaluate((el) => getComputedStyle(el).transitionDuration),
    '0s',
);
await browser.close();
await writeFile(
    new URL('checks.json', output),
    JSON.stringify(
        {
            renders,
            failures,
            errors,
            flows: ['discover dialog / Escape', 'reduced motion'],
            viewportScope:
                '320×480 to 1440×900; browser and PWA-sized viewports; no real device safe-area or keyboard verification',
        },
        null,
        2,
    ),
);
console.log(JSON.stringify({ renders, failures: failures.length, errors }));
assert.equal(failures.length, 0, JSON.stringify(failures.slice(0, 2)));
assert.equal(errors.length, 0);
