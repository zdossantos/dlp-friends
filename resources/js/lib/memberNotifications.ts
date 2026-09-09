type MessageNotice = {
    conversation_id: number;
    author_user_id: number;
};

type MatchNotice = {
    match_id: number;
};

type PersistentNotice = {
    id: string;
};

export function registerNotification(
    seen: Set<string>,
    notification: PersistentNotice,
): boolean {
    if (seen.has(notification.id)) {
        return false;
    }

    seen.add(notification.id);

    return true;
}

export function selectMatchNotification<T extends MatchNotice>(
    current: T | null,
    incoming: T,
): T {
    return current?.match_id === incoming.match_id ? current : incoming;
}

export function activeConversationId(url: string): number | null {
    const path = url.split(/[?#]/, 1)[0];
    const match = path.match(/^\/conversations\/(\d+)\/?$/);

    return match ? Number(match[1]) : null;
}

export function shouldShowMessageToast(
    message: MessageNotice,
    currentUserId: number,
    currentUrl: string,
): boolean {
    return (
        message.author_user_id !== currentUserId &&
        activeConversationId(currentUrl) !== message.conversation_id
    );
}
