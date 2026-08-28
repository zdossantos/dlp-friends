export type Locale = 'fr' | 'en';

export type TranslationMessages = {
    locale: {
        label: string;
        fr: string;
        en: string;
    };
    navigation: {
        settings: string;
        profile: string;
        discovery: string;
        conversations: string;
    };
    copy: Record<string, string>;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
