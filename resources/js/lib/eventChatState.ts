type IdentifiedMessage = { id: number };

export function mergeEventChatMessages<T extends IdentifiedMessage>(
    current: T[],
    incoming: T[],
): T[] {
    const byId = new Map(current.map((message) => [message.id, message]));

    for (const message of incoming) {
        byId.set(message.id, message);
    }

    return Array.from(byId.values()).sort((a, b) => a.id - b.id);
}

export function eventChatUnreadIncrement(
    message: { author_user_id: number },
    currentUserId: number,
    chatIsOpen: boolean,
): 0 | 1 {
    return message.author_user_id !== currentUserId && !chatIsOpen ? 1 : 0;
}
