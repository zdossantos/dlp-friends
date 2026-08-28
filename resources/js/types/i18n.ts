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
    onboarding: Record<string, string>;
    admin_onboarding: Record<string, string>;
    registration: Record<string, string>;
    match_dialog: Record<string, string>;
    stepper: Record<string, string>;
    copy: Record<string, string>;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
