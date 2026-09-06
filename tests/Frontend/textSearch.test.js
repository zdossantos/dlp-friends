import { describe, expect, test } from 'bun:test';
import { normalizeSearchText } from '../../resources/js/lib/textSearch';

describe('member name search', () => {
    test('ignores accents, case and surrounding spaces', () => {
        expect(normalizeSearchText('  ÉLODIE  ')).toBe('elodie');
    });
});
