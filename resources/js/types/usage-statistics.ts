export type UsageMetric =
    | 'registrations'
    | 'contributors'
    | 'likes'
    | 'passes'
    | 'matches'
    | 'privateMessages'
    | 'groupMessages'
    | 'events'
    | 'visits'
    | 'trips'
    | 'eventRegistrations'
    | 'tripRegistrations'
    | 'blocks'
    | 'reports';

export type UsageDay = { date: string } & Record<UsageMetric, number>;

export type UsageStatistics = {
    period: {
        from: string;
        to: string;
        previousFrom: string;
        previousTo: string;
        days: number;
    };
    daily: UsageDay[];
    metrics: Record<UsageMetric, { current: number; previous: number }>;
    activation: {
        registered: number;
        verified: number;
        profileCompleted: number;
        tutorialCompleted: number;
    };
    conversations: { total: number; withMessages: number };
    community: {
        members: number;
        enabledAccounts: number;
        bannedAccounts: number;
        pendingDeletion: number;
        hiddenProfiles: number;
    };
    interests: Array<{ name: string; count: number }>;
};

export const usageMetricColors: Record<UsageMetric, string> = {
    registrations: '#2563eb',
    contributors: '#475569',
    likes: '#d97706',
    passes: '#059669',
    matches: '#db2777',
    privateMessages: '#7c3aed',
    groupMessages: '#0891b2',
    events: '#dc2626',
    visits: '#4f46e5',
    trips: '#65a30d',
    eventRegistrations: '#c026d3',
    tripRegistrations: '#0d9488',
    blocks: '#ea580c',
    reports: '#9333ea',
};
