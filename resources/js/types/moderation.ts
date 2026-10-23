export type ConversationReport = {
    id: number;
    conversation_id: number;
    reason: string;
    details: string | null;
    decision: string | null;
    closed_at: string | null;
    created_at: string;
    reporter: { id: number | null; name: string };
    target: {
        id: number | null;
        name: string;
        banned: boolean;
        can_ban: boolean;
    };
};
export type ModerationPage<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type TripDepartureReport = Omit<
    ConversationReport,
    'conversation_id' | 'reason'
> & {
    scheduled_departure_at: string;
    left_at: string;
};
