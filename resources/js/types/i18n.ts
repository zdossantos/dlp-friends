export type Locale = 'fr' | 'en';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type TranslationMessages = {
    brand: TranslationTree;
    common: TranslationTree;
    account: TranslationTree;
    profile: TranslationTree;
    onboarding: TranslationTree;
    discovery: TranslationTree;
    conversations: TranslationTree;
    notifications: TranslationTree;
    events: TranslationTree;
    blocking: TranslationTree;
    moderation: TranslationTree;
    administration: TranslationTree;
    partners: TranslationTree;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
