import { describe, expect, test } from 'bun:test';
import { resolveAppMode } from '../../resources/js/lib/appMode';

describe('resolveAppMode', () => {
    test('identifies standard standalone display mode as PWA', () => {
        expect(resolveAppMode({ standaloneMedia: true, iosStandalone: false })).toBe('pwa');
    });

    test('identifies iOS standalone mode as PWA', () => {
        expect(resolveAppMode({ standaloneMedia: false, iosStandalone: true })).toBe('pwa');
    });

    test.each([
        { standaloneMedia: false, iosStandalone: false },
        { standaloneMedia: false },
    ])('uses browser when standalone signals are absent', (runtime) => {
        expect(resolveAppMode(runtime)).toBe('browser');
    });
});
