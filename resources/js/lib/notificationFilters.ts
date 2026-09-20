export const notificationCategories = [
    'conversations',
    'events',
    'partners',
] as const;

export type NotificationCategory = (typeof notificationCategories)[number];

export function applyNotificationFilters(
    category: NotificationCategory | null,
    unread: boolean,
): URLSearchParams {
    const parameters = new URLSearchParams();

    if (category !== null) {
        parameters.set('category', category);
    }

    if (unread) {
        parameters.set('unread', '1');
    }

    return parameters;
}
