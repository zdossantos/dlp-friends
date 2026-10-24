import type { PasskeyError } from '@laravel/passkeys';
import {
    InvalidDomainError,
    NotSupportedError,
    PasskeyExistsError,
    UserCancelledError,
} from '@laravel/passkeys';
import type { TranslationKey } from '@/composables/useTranslations';

export const localizePasskeyError = (
    error: PasskeyError | null,
    fallback: string | null,
    translate: (key: TranslationKey) => string,
): string | null => {
    if (!error && !fallback) {
        return null;
    }

    if (error instanceof UserCancelledError) {
        return translate('account.passkeys.cancelled');
    }

    if (error instanceof NotSupportedError) {
        return translate('account.passkeys.unsupported');
    }

    if (error instanceof PasskeyExistsError) {
        return translate('account.passkeys.exists');
    }

    if (error instanceof InvalidDomainError) {
        return translate('account.passkeys.invalid_domain');
    }

    return translate('account.passkeys.failed');
};
