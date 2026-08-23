import type { VisitFrequency } from './auth';

export type DiscoveryProfile = {
    userId: number;
    profileId: number;
    displayName: string;
    age: number;
    bio: string | null;
    visitFrequency: VisitFrequency | null;
    commonPassionCount: number;
    commonPassions: string[];
    frequencyBonus: boolean;
    score: number;
};

export type SwipeDecision = 'like' | 'pass';

export type DiscoveryMatch = {
    id: number;
    displayName: string;
};
