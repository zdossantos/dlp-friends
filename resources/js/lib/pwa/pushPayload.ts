export type PushPayload = {
    title: string;
    body: string;
    target: string;
    notificationId: string;
    unreadCount?: number;
};

const allowedPrefixes = [
    '/notifications',
    '/conversations/',
    '/events/',
    '/partner/',
    '/admin/',
];

export const safePushTarget = (value: unknown): string => {
    if (
        typeof value !== 'string' ||
        !value.startsWith('/') ||
        value.startsWith('//')
    ) {
        return '/notifications';
    }

    try {
        const parsed = new URL(value, 'https://local.invalid');

        if (parsed.origin !== 'https://local.invalid') {
            return '/notifications';
        }
    } catch {
        return '/notifications';
    }

    return allowedPrefixes.some(
        (prefix) =>
            value === prefix.replace(/\/$/, '') || value.startsWith(prefix),
    )
        ? value
        : '/notifications';
};

export const parsePushPayload = (value: unknown): PushPayload => {
    const payload =
        value !== null && typeof value === 'object'
            ? (value as Record<string, unknown>)
            : {};

    return {
        title:
            typeof payload.title === 'string' && payload.title.length <= 80
                ? payload.title
                : 'DLP Friends',
        body:
            typeof payload.body === 'string' && payload.body.length <= 180
                ? payload.body
                : 'Tu as une nouvelle notification.',
        target: safePushTarget(payload.target),
        notificationId:
            typeof payload.notification_id === 'string'
                ? payload.notification_id
                : crypto.randomUUID(),
        unreadCount:
            typeof payload.unread_count === 'number' &&
            payload.unread_count >= 0
                ? payload.unread_count
                : undefined,
    };
};
