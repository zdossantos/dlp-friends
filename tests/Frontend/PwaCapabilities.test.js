import { describe, expect, test } from 'bun:test';
import {
    createSingleReloadHandler,
    detectPwaState,
    isIosDevice,
    isSafeExternalUrl,
} from '../../resources/js/lib/pwa/capabilities';

describe('PWA capabilities', () => {
    test.each([
        [{ standaloneMedia: true, iosStandalone: false }, 'standalone'],
        [{ standaloneMedia: false, iosStandalone: true }, 'standalone'],
        [
            {
                standaloneMedia: false,
                iosStandalone: false,
                installed: true,
            },
            'installed',
        ],
        [
            {
                standaloneMedia: false,
                iosStandalone: false,
                installPromptAvailable: true,
            },
            'installable',
        ],
        [
            {
                standaloneMedia: false,
                iosStandalone: false,
                serviceWorkerSupported: true,
            },
            'browser',
        ],
        [
            {
                standaloneMedia: false,
                iosStandalone: false,
                serviceWorkerSupported: false,
            },
            'unsupported',
        ],
    ])('detects runtime state %#', (capabilities, expected) => {
        expect(detectPwaState(capabilities)).toBe(expected);
    });

    test('reloads only once when the new worker takes control', () => {
        let reloads = 0;
        const onControllerChange = createSingleReloadHandler(() => reloads++);

        onControllerChange();
        onControllerChange();

        expect(reloads).toBe(1);
    });

    test.each([
        ['https://example.com/path', true],
        ['http://example.com/path', false],
        ['/internal', false],
        ['javascript:alert(1)', false],
    ])('accepts only external HTTPS URLs', (url, expected) => {
        expect(isSafeExternalUrl(url, 'https://dlp-friends.test')).toBe(
            expected,
        );
    });

    test('detects modern iPads reporting a desktop user agent', () => {
        expect(
            isIosDevice({
                userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15)',
                platform: 'MacIntel',
                maxTouchPoints: 5,
            }),
        ).toBe(true);
        expect(
            isIosDevice({
                userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15)',
                platform: 'MacIntel',
                maxTouchPoints: 0,
            }),
        ).toBe(false);
    });
});
