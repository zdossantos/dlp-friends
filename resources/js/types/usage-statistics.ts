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
