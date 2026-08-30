export type Locale = 'fr' | 'en';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type TranslationMessages = {
    common: TranslationTree;
    account: TranslationTree;
    profile: TranslationTree;
    discovery: TranslationTree;
    conversations: TranslationTree;
    administration: TranslationTree;
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
        back: string;
    };
    mail: Record<string, string>;
    onboarding: Record<string, string>;
    admin_onboarding: Record<string, string>;
    registration: Record<string, string>;
    match_dialog: Record<string, string>;
    messaging: Record<string, string>;
    blocking: Record<string, string>;
    stepper: Record<string, string>;
    copy: Record<string, string>;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
