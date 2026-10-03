import type { InterestOption } from './interest';

export type User = {
    id: number;
    email: string;
    locale: 'fr' | 'en' | null;
    show_presence: boolean;
    email_verified_at: string | null;
    profile: Profile | null;
    roles: Array<{ name: RoleName }>;
    two_factor_enabled?: boolean;
    has_usable_password: boolean;
    [key: string]: unknown;
};

export type RoleName = 'user' | 'admin' | 'partner';
export type VisitFrequency = 'rarely' | 'sometimes' | 'often' | 'very_often';
export type SocialNetwork =
    'instagram' | 'facebook' | 'tiktok' | 'youtube' | 'x';
export type SocialLink = { network: SocialNetwork; url: string };
export type SocialLinksVisibility = 'hidden' | 'matches' | 'members';

export type ProfileVisibility = 'visible' | 'hidden';

export type AvatarOption = {
    id: number;
    name: string;
    image_url: string;
    primary_color: string;
    secondary_color: string;
};

export type Profile = {
    avatar_id?: number | null;
    avatar?: AvatarOption | null;
    display_name: string;
    bio: string | null;
    visit_frequency: VisitFrequency | null;
    visibility: ProfileVisibility;
    onboarding_completed_at: string | null;
    interests?: InterestOption[];
    social_links?: SocialLink[];
    social_links_visibility?: SocialLinksVisibility;
};

export type Auth = {
    user: User;
    unread_notifications_count: number;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
