export type EventChatAccessChanged = {
    event_id: number;
    access: 'granted' | 'revoked' | 'read_only';
};

export function eventChatAccessReloadOptions(
    _change: EventChatAccessChanged,
): {
    only: ['organized', 'participating', 'panel'];
    preserveScroll: true;
} {
    return {
        only: ['organized', 'participating', 'panel'],
        preserveScroll: true,
    };
}

export function isEventWorkspaceUrl(url: string): boolean {
    const path = url.split(/[?#]/, 1)[0];

    return path === '/events' || path.startsWith('/events/');
}
