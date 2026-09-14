import type { NotificationCategory } from '@/lib/notificationFilters';

export type MemberNotification = {
    id: string;
    category: NotificationCategory;
    translation_key:
        | 'notifications.items.new_match'
        | 'notifications.items.new_message'
        | `notifications.items.event_${string}`;
    parameters: Record<string, string | number>;
    target_url: string;
    read_at: string | null;
    created_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type NotificationPage = {
    data: MemberNotification[];
    links: PaginationLink[];
    total: number;
};
