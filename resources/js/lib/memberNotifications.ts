type MessageNotice = {
    conversation_id: number;
    author_user_id: number;
};

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
