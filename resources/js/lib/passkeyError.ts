import type { PasskeyError } from '@laravel/passkeys';
import { UserCancelledError } from '@laravel/passkeys';
import type { TranslationKey } from '@/composables/useTranslations';

export const localizePasskeyError = (
    error: PasskeyError | null,
    fallback: string | null,
    translate: (key: TranslationKey) => string,
): string | null =>
    error instanceof UserCancelledError
        ? translate('account.passkeys.cancelled')
        : fallback;
