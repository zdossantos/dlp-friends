export type AnalyticsPage = {
    pageType: string;
    pageTitle: string;
    pagePath: string;
};

const identifierSegment =
    /^\d+$|^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

const titles = {
    fr: {
        conversation_list: 'Mes échanges — DLP Friends',
        conversation_detail: 'Conversation — DLP Friends',
        member_profile: 'Profil membre — DLP Friends',
        own_profile: 'Mon profil — DLP Friends',
        event_participant: 'Participant — DLP Friends',
        event_detail: 'Événement — DLP Friends',
    },
    en: {
        conversation_list: 'My conversations — DLP Friends',
        conversation_detail: 'Conversation — DLP Friends',
        member_profile: 'Member profile — DLP Friends',
        own_profile: 'My profile — DLP Friends',
        event_participant: 'Participant — DLP Friends',
        event_detail: 'Event — DLP Friends',
    },
} as const;

type KnownPageType = keyof (typeof titles)['fr'];

const routes: Array<{ pattern: RegExp; pageType: KnownPageType }> = [
    { pattern: /^\/conversations$/, pageType: 'conversation_list' },
    { pattern: /^\/conversations\/\{id\}$/, pageType: 'conversation_detail' },
    { pattern: /^\/members\/\{id\}$/, pageType: 'member_profile' },
    { pattern: /^\/profile$/, pageType: 'own_profile' },
    {
        pattern: /^\/events\/\{id\}\/participants\/\{id\}$/,
        pageType: 'event_participant',
    },
    { pattern: /^\/events\/\{id\}$/, pageType: 'event_detail' },
];

export function normalizeAnalyticsPath(path: string): string {
    const pathname = path.split(/[?#]/, 1)[0] || '/';

    return pathname
        .split('/')
        .map((segment) => (identifierSegment.test(segment) ? '{id}' : segment))
        .join('/');
}

export function resolveAnalyticsPage(
    path: string,
    locale: string,
): AnalyticsPage {
    const normalizedPath = normalizeAnalyticsPath(path);
    const route = routes.find(({ pattern }) => pattern.test(normalizedPath));

    if (!route) {
        return {
            pageType: 'application_page',
            pageTitle: 'DLP Friends',
            pagePath: '/application',
        };
    }

    const catalogue = locale.toLowerCase().startsWith('en')
        ? titles.en
        : titles.fr;

    return {
        pageType: route.pageType,
        pageTitle: catalogue[route.pageType],
        pagePath: normalizedPath,
    };
}
