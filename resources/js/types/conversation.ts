import type { AvatarOption } from './auth';

export type ConversationMessage = {
    id: number;
    conversation_id: number;
    author_user_id: number;
    content: string;
    created_at: string | null;
};

export type ConversationParticipant = {
    id: number;
    display_name: string;
    avatar: AvatarOption;
};

export type ConversationSummary = {
    id: number;
    participant: ConversationParticipant;
    archived_at: string | null;
    latest_message: ConversationMessage | null;
    activity_at: string | null;
};

export type ConversationDetails = {
    id: number;
    archived_at: string | null;
};

export type PaginatedMessages = {
    data: ConversationMessage[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
