import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/analyticsConsent.ts',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    preload: [{ weight: 400, style: 'normal' }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('Cinzel Decorative', {
                    weights: [700],
                    preload: false,
                    fallbacks: ['ui-serif', 'Georgia', 'serif'],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'service-worker.ts',
            outDir: 'public',
            injectRegister: null,
            manifest: false,
            injectManifest: {
                globPatterns: [
                    'build/assets/**/*.{css,js,woff,woff2}',
                    'pwa/icon-*.png',
                    'favicon.svg',
                    'offline.html',
                ],
            },
        }),
    ],
});
