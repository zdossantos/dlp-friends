import { describe, expect, test } from 'bun:test';

import { localizeMailError } from '../../resources/js/lib/mailError';

describe('mail form errors', () => {
    test('retranslates a stable backend error when the active locale changes', () => {
        const french = (key) => `fr:${key}`;
        const english = (key) => `en:${key}`;

        expect(localizeMailError('mail.rate_limited', french)).toBe(
            'fr:account.email_delivery.rate_limited',
        );
        expect(localizeMailError('mail.rate_limited', english)).toBe(
            'en:account.email_delivery.rate_limited',
        );
    });

    test('preserves regular validation messages from Laravel', () => {
        expect(
            localizeMailError('The email field is required.', () => ''),
        ).toBe('The email field is required.');
    });
});
