import type { AvatarOption, VisitFrequency } from './auth';

export type PublicMember = {
    id: number;
    is_admin: boolean;
    display_name: string;
    age: number;
    avatar: AvatarOption;
    bio: string | null;
    visit_frequency: VisitFrequency | null;
    interests: Array<{ id: number; name: string }>;
};
