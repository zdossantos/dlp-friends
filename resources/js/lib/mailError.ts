import type { TranslationKey } from '@/composables/useTranslations';

const translationKeys = {
    'mail.delivery_failed': 'account.email_delivery.failed',
    'mail.rate_limited': 'account.email_delivery.rate_limited',
} as const satisfies Record<string, TranslationKey>;

export const localizeMailError = (
    message: string | undefined,
    translate: (key: TranslationKey) => string,
): string | undefined => {
    if (message === undefined || !(message in translationKeys)) {
        return message;
    }

    return translate(translationKeys[message as keyof typeof translationKeys]);
};
