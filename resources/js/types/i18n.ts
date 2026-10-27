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
    visits: TranslationTree;
    trips: TranslationTree;
    outings: TranslationTree;
    blocking: TranslationTree;
    moderation: TranslationTree;
    administration: TranslationTree;
    usage: TranslationTree;
    partners: TranslationTree;
    application_announcements: TranslationTree;
};

export type I18n = {
    locale: Locale;
    messages: TranslationMessages;
};
