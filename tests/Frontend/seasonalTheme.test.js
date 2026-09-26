import { describe, expect, test } from 'bun:test';
import {
    millisecondsUntilTransition,
    seasonalThemeClass,
} from '../../resources/js/lib/seasonalTheme';

describe('seasonal themes', () => {
    test('returns the class for each active theme', () => {
        expect(seasonalThemeClass('halloween')).toBe('seasonal-halloween');
        expect(seasonalThemeClass('christmas')).toBe('seasonal-christmas');
        expect(seasonalThemeClass(null)).toBeNull();
    });

    test('bounds long transition delays to the browser timeout maximum', () => {
        expect(
            millisecondsUntilTransition(
                '2027-12-01T00:00:00Z',
                Date.parse('2026-01-01T00:00:00Z'),
            ),
        ).toBe(2_147_000_000);
    });

    test('returns immediate, finite delays and ignores missing transitions', () => {
        const now = Date.parse('2026-10-31T23:00:00Z');

        expect(
            millisecondsUntilTransition('2026-10-31T23:00:01Z', now),
        ).toBe(1_000);
        expect(
            millisecondsUntilTransition('2026-10-31T22:00:00Z', now),
        ).toBe(0);
        expect(millisecondsUntilTransition(null, now)).toBeNull();
        expect(millisecondsUntilTransition('not-a-date', now)).toBeNull();
    });
});
