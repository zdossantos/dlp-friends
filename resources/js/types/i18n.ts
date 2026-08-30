export type Locale = 'fr' | 'en';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type TranslationMessages = {
    common: TranslationTree;
    account: TranslationTree;
    profile: TranslationTree;
    onboarding: TranslationTree;
    discovery: TranslationTree;
    conversations: TranslationTree;
    blocking: TranslationTree;
    administration: TranslationTree;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
