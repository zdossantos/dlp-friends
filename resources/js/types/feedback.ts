export type FeedbackOptions = {
    kinds: string[];
    areas: string[];
    statuses?: string[];
};
export type FeedbackSummary = {
    id: number;
    kind: string;
    area: string;
    status: string;
    created_at: string;
    author: { id: number; name: string };
};
export type FeedbackDetail = FeedbackSummary & {
    content: string;
    internal_note: string | null;
};
