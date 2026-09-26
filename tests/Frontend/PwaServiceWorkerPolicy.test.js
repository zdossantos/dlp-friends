import { describe, expect, test } from 'bun:test';
import { isCacheableStaticRequest } from '@/lib/pwa/serviceWorkerPolicy';

describe('service worker cache policy', () => {
    test.each([
        ['navigation', new Request('https://dlp.test/app', { headers: { accept: 'text/html' } })],
        ['inertia', new Request('https://dlp.test/discovery', { headers: { 'x-inertia': 'true' } })],
        ['authorized', new Request('https://dlp.test/build/app-ABC123.js', { headers: { authorization: 'Bearer secret' } })],
        ['storage', new Request('https://dlp.test/storage/avatars/private.jpg')],
        ['api', new Request('https://dlp.test/api/notifications')],
        ['member image', new Request('https://dlp.test/member-profiles/42/image')],
        ['mutation', new Request('https://dlp.test/build/app-ABC123.js', { method: 'POST' })],
    ])('never caches %s requests', (_label, request) => {
        expect(isCacheableStaticRequest(request)).toBeFalse();
    });

    test.each([
        '/build/assets/app-ABC123.js',
        '/build/assets/app-ABC123.css',
        '/build/assets/instrument-sans-ABC123.woff2',
        '/pwa/icon-192.png',
        '/favicon.svg',
        '/offline.html',
    ])('allows the public static asset %s', (path) => {
        expect(isCacheableStaticRequest(new Request(`https://dlp.test${path}`))).toBeTrue();
    });
});
