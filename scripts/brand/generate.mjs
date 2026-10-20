// Explicit generation only: bun scripts/brand/generate.mjs
// Uses the project's existing Playwright dependency and installed Chromium.
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile, copyFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const output = path.join(root, 'public');
const source = await readFile(
    new URL('./horizon-source.svg', import.meta.url),
    'utf8',
);
const paths = [...source.matchAll(/<path[^>]* d="([^"]+)"/g)].map(
    (match) => match[1],
);

if (paths.length !== 4) {
    throw new Error('The approved Horizon source must contain four paths.');
}

const variants = [
    ['standard-clair', '', '#FAF7F2', '#39215A', '#642BBF', '#39215A'],
    ['standard-sombre', '-dark', '#14101D', '#E9DFF5', '#D6B773', '#FAF7F2'],
    [
        'halloween-clair',
        '-halloween',
        '#FAF5EF',
        '#522A75',
        '#B6530C',
        '#39215A',
    ],
    [
        'halloween-sombre',
        '-halloween-dark',
        '#17101A',
        '#D7B4EF',
        '#FA9D4B',
        '#FFF4E5',
    ],
    ['noel-clair', '-christmas', '#FAF7EF', '#24583F', '#961839', '#214333'],
    [
        'noel-sombre',
        '-christmas-dark',
        '#0C1812',
        '#BDDFCC',
        '#E4C778',
        '#F6F2E8',
    ],
];
const svg = (content, box = '0 0 256 256') =>
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${box}">${content}</svg>\n`;
function mark(ink, accent) {
    return `<defs><mask id="cut" maskUnits="userSpaceOnUse" x="-16" y="-16" width="288" height="288"><rect x="-16" y="-16" width="288" height="288" fill="white"/><path d="${paths[1]}" fill="none" stroke="black" stroke-width="23" stroke-linecap="round" stroke-linejoin="round"/></mask></defs><path d="${paths[0]}" fill="none" stroke="${ink}" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" mask="url(#cut)"/><path d="${paths[2]}" fill="none" stroke="${accent}" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/><path d="${paths[3]}" fill="${accent}"/>`;
}
// Outlined Instrument Sans label; no system font is needed to regenerate icons.
const betaLabel = (
    await readFile(new URL('./beta-label.svg', import.meta.url), 'utf8')
).replace(/<svg[^>]*>|<\/svg>|<!--[\s\S]*?-->/g, '');
function badge(maskable) {
    const x = maskable ? 128 : 139;
    const y = maskable ? 166 : 180;

    return `<g aria-label="BETA"><rect x="${x}" y="${y}" width="68" height="29" rx="14.5" fill="#E9DFF5" stroke="#39215A" stroke-width="2"/><g transform="translate(${x + 34} ${y + 19})" fill="#39215A">${betaLabel}</g></g>`;
}
function icon(bg, ink, accent, beta, maskable, rounded = false) {
    const size = maskable ? 142 : 190;
    const inset = (256 - size) / 2;

    return svg(
        `<rect width="256" height="256" ${rounded ? 'rx="48"' : ''} fill="${bg}"/><g transform="translate(${inset} ${inset}) scale(${size / 256})">${mark(ink, accent)}</g>${beta ? badge(maskable) : ''}`,
    );
}
async function save(relative, data) {
    const filename = path.join(output, relative);
    await mkdir(path.dirname(filename), { recursive: true });
    await writeFile(filename, data);
}
const browser = await chromium.launch();

try {
    const page = await browser.newPage({ deviceScaleFactor: 1 });
    async function raster(art, size) {
        await page.setViewportSize({ width: size, height: size });
        await page.setContent(
            `<style>html,body{margin:0;width:100%;height:100%;overflow:hidden}img{display:block;width:100%;height:100%}</style><img src="data:image/svg+xml;base64,${Buffer.from(art).toString('base64')}">`,
        );
        await page.locator('img').evaluate((image) => image.decode());

        return page.screenshot({ omitBackground: true });
    }

    for (const [key, suffix, bg, ink, accent, foreground] of variants) {
        const dir = `brand/variants/${key}`;
        await save(
            `brand/dlp-friends-logo${suffix}.svg`,
            svg(mark(ink, accent)),
        );
        await save(`${dir}/symbole.svg`, svg(mark(ink, accent)));
        // Editable signatures retain the approved preview typography; application signatures use its existing font-accent token.
        await save(
            `${dir}/signature-horizontal.svg`,
            svg(
                `<g transform="translate(12 12) scale(.55)">${mark(ink, accent)}</g><text x="184" y="100" font-family="Arial,sans-serif" font-size="49" font-weight="600" letter-spacing="-1.5" fill="${foreground}">DLP Friends</text>`,
                '0 0 510 166',
            ),
        );
        await save(
            `${dir}/signature-vertical.svg`,
            svg(
                `<g transform="translate(78 12) scale(.72)">${mark(ink, accent)}</g><text x="170" y="250" font-family="Arial,sans-serif" text-anchor="middle" font-size="39" font-weight="600" fill="${foreground}">DLP Friends</text>`,
                '0 0 340 280',
            ),
        );

        for (const beta of [false, true]) {
            const prefix = beta ? 'beta-' : '';
            await save(
                `${dir}/${prefix}favicon.svg`,
                icon(bg, ink, accent, beta, false, true),
            );
            // Raster operations share one page and must remain sequential.
            const favicon = icon(bg, ink, accent, beta, false, true);
            const images = [];

            for (const size of [16, 32, 48]) {
                images.push({ size, data: await raster(favicon, size) });
            }

            const header = Buffer.alloc(54);
            header.writeUInt16LE(1, 2);
            header.writeUInt16LE(3, 4);
            let offset = 54;

            for (const [i, image] of images.entries()) {
                const pos = 6 + i * 16;
                header[pos] = header[pos + 1] = image.size;
                header.writeUInt16LE(1, pos + 4);
                header.writeUInt16LE(32, pos + 6);
                header.writeUInt32LE(image.data.length, pos + 8);
                header.writeUInt32LE(offset, pos + 12);
                offset += image.data.length;
            }

            await save(
                `${dir}/${prefix}favicon.ico`,
                Buffer.concat([header, ...images.map((image) => image.data)]),
            );
            // Apple applies its own system mask: provide a full opaque square.
            await save(
                `${dir}/${prefix}apple-touch-icon.png`,
                await raster(icon(bg, ink, accent, beta, false), 180),
            );

            for (const maskable of [false, true]) {
                const name = maskable ? 'icon-maskable' : 'icon';
                const art = icon(bg, ink, accent, beta, maskable);
                await save(`${dir}/${prefix}${name}.svg`, art);

                for (const size of [192, 512]) {
                    await save(
                        `${dir}/${prefix}${name}-${size}.png`,
                        await raster(art, size),
                    );
                }
            }
        }
    }

    for (const [name, color] of [
        ['black', '#000000'],
        ['white', '#FFFFFF'],
    ]) {
        await save(
            `brand/dlp-friends-logo-${name}.svg`,
            svg(mark(color, color)),
        );
    }

    await save(
        'pwa/badge-96.png',
        await raster(svg(mark('#FFFFFF', '#FFFFFF')), 96),
    );

    for (const beta of [false, true]) {
        const prefix = beta ? 'beta/' : '';
        const variantPrefix = beta ? 'beta-' : '';

        for (const file of [
            'favicon.svg',
            'favicon.ico',
            'apple-touch-icon.png',
            'icon-192.png',
            'icon-512.png',
            'icon-maskable-192.png',
            'icon-maskable-512.png',
        ]) {
            const destination = `${prefix}${file.startsWith('icon-') ? 'pwa/' : ''}${file}`;
            await mkdir(path.dirname(path.join(output, destination)), {
                recursive: true,
            });
            await copyFile(
                path.join(
                    output,
                    `brand/variants/standard-sombre/${variantPrefix}${file}`,
                ),
                path.join(output, destination),
            );
        }
    }
} finally {
    await browser.close();
}

console.log(
    'Horizon: six variants, monochromes, signatures, favicons, Apple, PWA, beta and notification badge generated.',
);

// Keep the beta manifest in sync with production metadata and localized app name.
const manifest = JSON.parse(
    await readFile(path.join(output, 'app.webmanifest'), 'utf8'),
);
const translations = JSON.parse(
    execFileSync(
        'php',
        [
            '-r',
            'echo json_encode(require $argv[1]);',
            path.join(root, 'lang/fr/brand.php'),
        ],
        { encoding: 'utf8' },
    ),
);
manifest.id = '/beta';
manifest.name = manifest.short_name = translations.beta_app_name;
manifest.icons = manifest.icons.map((entry) => ({
    ...entry,
    src: `/beta${entry.src}`,
}));
await save('beta/app.webmanifest', `${JSON.stringify(manifest, null, 4)}\n`);
