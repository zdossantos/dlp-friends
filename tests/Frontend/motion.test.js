import { describe, expect, test } from 'bun:test';
import { prefersReducedMotion } from '../../resources/js/lib/motion';

describe('prefersReducedMotion', () => {
    test('reports the supplied reduced-motion preference', () => {
        expect(prefersReducedMotion({ matches: true })).toBe(true);
        expect(prefersReducedMotion({ matches: false })).toBe(false);
    });

    test('falls back safely when matchMedia is unavailable', () => {
        expect(prefersReducedMotion()).toBe(false);
    });
});
