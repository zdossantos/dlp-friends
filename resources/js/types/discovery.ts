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
    interests: Array<{
        name: string;
        isCommon: boolean;
    }>;
    frequencyBonus: boolean;
    score: number;
};

export type DiscoveryCardProfile = Omit<
    DiscoveryProfile,
    'userId' | 'profileId' | 'score' | 'avatar'
> & {
    avatar: Omit<AvatarOption, 'id'>;
};

export type SwipeDecision = 'like' | 'pass';

export type DiscoveryMatch = {
    id: number;
    conversationId: number;
    displayName: string;
};
