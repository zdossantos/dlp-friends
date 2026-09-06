import { PasskeyError, UserCancelledError } from '@laravel/passkeys';
import { describe, expect, test } from 'bun:test';

import { localizePasskeyError } from '../../resources/js/lib/passkeyError';

describe('passkey errors', () => {
    test('translates a cancelled operation', () => {
        expect(
            localizePasskeyError(
                new UserCancelledError(),
                'The passkey operation was cancelled.',
                () => 'L’opération avec la clé d’accès a été annulée.',
            ),
        ).toBe('L’opération avec la clé d’accès a été annulée.');
    });

    test('preserves every other passkey error', () => {
        expect(
            localizePasskeyError(
                new PasskeyError('Service unavailable.'),
                'Service unavailable.',
                () => 'Traduction inutilisée',
            ),
        ).toBe('Service unavailable.');
    });
});
