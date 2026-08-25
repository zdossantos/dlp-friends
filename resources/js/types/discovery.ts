import type { AvatarOption, VisitFrequency } from './auth';

export type DiscoveryProfile = {
    userId: number;
    profileId: number;
    displayName: string;
    avatar: AvatarOption;
    age: number;
    bio: string | null;
    visitFrequency: VisitFrequency | null;
    commonInterestCount: number;
    commonInterests: string[];
    frequencyBonus: boolean;
    score: number;
};

export type SwipeDecision = 'like' | 'pass';

export type DiscoveryMatch = {
    id: number;
    displayName: string;
};
