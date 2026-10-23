export type GroupChatInfo = {
    id: number;
    isReadOnly: boolean;
    readOnlyReason: 'cancelled' | 'archived' | null;
};

export type GroupChatMessage = {
    id: number;
    group_chat_id: number;
    system_type: 'joined' | 'left' | 'removed' | null;
    author_user_id: number;
    content: string;
    reaction_count: number;
    reacted_by_current_user: boolean;
    author: { id: number; display_name: string };
    created_at: string;
    updated_at?: string;
};

export type PaginatedGroupChatMessages = {
    data: GroupChatMessage[];
    next_page_url: string | null;
    prev_page_url: string | null;
};

export type GroupConversationSummary = GroupChatInfo & {
    event_id: number | null;
    context: 'event' | 'trip';
    href: string;
    title: string;
    unread_count: number;
};
