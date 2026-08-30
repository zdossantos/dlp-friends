import { expect, test } from 'bun:test';
import { translationFor } from '../../resources/js/composables/useTranslations';

test('nested translation keys resolve to a leaf string', () => {
    const messages = {
        discovery: { match: { title: 'Your worlds cross paths' } },
    };

    expect(translationFor(messages, 'discovery.match.title')).toBe(
        'Your worlds cross paths',
    );
});
