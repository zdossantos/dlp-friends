import type { RoleName } from './auth';

export type AdminMember = {
    id: number;
    display_name: string | null;
    public_profile_url: string | null;
    email: string;
    status: string;
    visibility: string | null;
    created_at: string | null;
    email_verified_at: string | null;
    is_admin: boolean;
    roles: Array<{ name: RoleName }>;
    likes_sent_count: number;
    likes_received_count: number;
    passes_sent_count: number;
    passes_received_count: number;
    matches_count: number;
    messages_sent_count: number;
    blocked_count: number;
    blocked_by_count: number;
    can_ban: boolean;
    can_delete: boolean;
    can_start_conversation: boolean;
    can_manage_roles: boolean;
};

export type OnboardingMember = {
    id: number;
    display_name: string;
    email: string;
    status: 'not_started' | 'in_progress' | 'completed';
    step: string | null;
    updated_at: string;
};
