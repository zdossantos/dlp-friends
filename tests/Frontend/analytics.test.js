import { describe, expect, test } from 'bun:test';
import { normalizeAnalyticsPath } from '../../resources/js/lib/analytics';

describe('normalizeAnalyticsPath', () => {
    test('keeps public and static application paths unchanged', () => {
        expect(normalizeAnalyticsPath('/fr/matching')).toBe('/fr/matching');
        expect(normalizeAnalyticsPath('/settings/profile')).toBe('/settings/profile');
    });

    test('removes identifiers from analytics paths', () => {
        expect(normalizeAnalyticsPath('/conversations/42')).toBe('/conversations/{id}');
        expect(
            normalizeAnalyticsPath(
                '/profiles/0198f30e-7b67-7260-9c7d-4f15d4da0d31',
            ),
        ).toBe('/profiles/{id}');
    });

    test('never includes query parameters or fragments', () => {
        expect(normalizeAnalyticsPath('/discover?page=2#profile')).toBe(
            '/discover',
        );
    });
});
