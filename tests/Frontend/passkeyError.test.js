import {
    InvalidDomainError,
    NotSupportedError,
    PasskeyExistsError,
    PasskeyError,
    UserCancelledError,
} from '@laravel/passkeys';
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

    test('replaces unclassified technical errors with a translated message', () => {
        expect(
            localizePasskeyError(
                new PasskeyError('Service unavailable.'),
                'Service unavailable.',
                (key) => key,
            ),
        ).toBe('account.passkeys.failed');
    });
});

test('successful passkey state has no error', () => {
    expect(localizePasskeyError(null, null, (key) => key)).toBeNull();
});

for (const [error, key] of [
    [new NotSupportedError(), 'account.passkeys.unsupported'],
    [new PasskeyExistsError(), 'account.passkeys.exists'],
    [new InvalidDomainError('private-host'), 'account.passkeys.invalid_domain'],
]) {
    test(`classifies ${error.name} without exposing technical details`, () => {
        expect(localizePasskeyError(error, error.message, (key) => key)).toBe(
            key,
        );
    });
}
