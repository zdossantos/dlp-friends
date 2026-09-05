import { describe, expect, test } from 'bun:test';
import { resolvePageTitle } from '../../resources/js/lib/pageTitle';

describe('resolvePageTitle', () => {
    test('falls back to the product name when the build-time name is missing', () => {
        expect(resolvePageTitle('Connexion', undefined)).toBe(
            'Connexion - DLP Friends',
        );
        expect(resolvePageTitle('', undefined)).toBe('DLP Friends');
    });

    test('uses the configured application name', () => {
        expect(resolvePageTitle('Connexion', 'Friends Test')).toBe(
            'Connexion - Friends Test',
        );
    });
});
