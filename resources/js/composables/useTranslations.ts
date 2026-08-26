import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { I18n, Locale, TranslationMessages } from '@/types/i18n';

type TranslationKey =
    | 'locale.label'
    | 'locale.fr'
    | 'locale.en'
    | 'navigation.settings'
    | 'navigation.profile'
    | 'navigation.discovery';

const translationFor = (
    messages: TranslationMessages,
    key: TranslationKey,
): string => {
    const [group, item] = key.split('.') as [keyof TranslationMessages, string];

    return messages[group][item as never];
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
