type IdentifiedMessage = { id: number };

export function mergeGroupChatMessages<T extends IdentifiedMessage>(
    current: T[],
    incoming: T[],
): T[] {
    const byId = new Map(current.map((message) => [message.id, message]));

    for (const message of incoming) {
        byId.set(message.id, message);
    }

    return Array.from(byId.values()).sort((a, b) => a.id - b.id);
}

export function groupChatUnreadIncrement(
    message: { author_user_id: number },
    currentUserId: number,
    chatIsOpen: boolean,
): 0 | 1 {
    return message.author_user_id !== currentUserId && !chatIsOpen ? 1 : 0;
}

export function resolveGroupChatDraft(
    current: unknown,
    legacy: unknown,
    stored: string | null,
): { content: string } {
    for (const candidate of [
        current,
        stored === null ? undefined : { content: stored },
        legacy,
    ]) {
        if (
            candidate !== null &&
            typeof candidate === 'object' &&
            'content' in candidate &&
            typeof candidate.content === 'string'
        ) {
            return { content: candidate.content };
        }
    }

    return { content: '' };
}
