import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type {
    I18n,
    Locale,
    TranslationMessages,
    TranslationTree,
} from '@/types/i18n';

type TranslationGroup = keyof TranslationMessages;
export type TranslationKey = `${TranslationGroup & string}.${string}`;

export const translationFor = (
    messages: TranslationMessages,
    key: TranslationKey,
): string => {
    const translation = key
        .split('.')
        .reduce<string | TranslationTree>((current, segment) => {
            if (typeof current === 'string' || current[segment] === undefined) {
                throw new Error(`Missing translation: ${key}`);
            }

            return current[segment];
        }, messages);

    if (typeof translation !== 'string') {
        throw new Error(`Translation is not a string: ${key}`);
    }

    return translation;
};

export function useTranslations(): {
    locale: ComputedRef<Locale>;
    t: (
        key: TranslationKey,
        replacements?: Record<string, string | number>,
    ) => string;
    formatDate: (
        value: string | Date,
        options?: Intl.DateTimeFormatOptions,
    ) => string;
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
} {
    const page = usePage<{ i18n: I18n }>();
    const locale = computed(() => page.props.i18n.locale);

    const t = (
        key: TranslationKey,
        replacements: Record<string, string | number> = {},
    ): string =>
        Object.entries(replacements).reduce(
            (message, [replacement, value]) =>
                message.replaceAll(`:${replacement}`, String(value)),
            translationFor(page.props.i18n.messages, key),
        );

    return {
        locale,
        t,
        formatDate: (value, options) =>
            new Intl.DateTimeFormat(locale.value, options).format(
                new Date(value),
            ),
        formatNumber: (value, options) =>
            new Intl.NumberFormat(locale.value, options).format(value),
    };
}
