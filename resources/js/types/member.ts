import type { AvatarOption, SocialLink, VisitFrequency } from './auth';

export type PublicMember = {
    id: number;
    is_admin: boolean;
    display_name: string;
    age: number;
    avatar: AvatarOption;
    bio: string | null;
    social_links?: SocialLink[];
    visit_frequency: VisitFrequency | null;
    interests: Array<{ id: number; name: string }>;
};

type AvailableEmbeddedMemberProfile = {
    receivedReportsHref?: string;
    isBlocked: false;
    member: PublicMember;
    canBlock: boolean;
    canLike: boolean;
    canUnblock: boolean;
    conversationHref: string | null;
};

type BlockedEmbeddedMemberProfile = {
    isBlocked: true;
    member: { id: number };
    canBlock: false;
    canLike: false;
    canUnblock: boolean;
    conversationHref: null;
};

export type EmbeddedMemberProfile =
    AvailableEmbeddedMemberProfile | BlockedEmbeddedMemberProfile;
