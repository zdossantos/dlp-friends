import type {
    ConversationMessage,
    ConversationSummary,
    MessagesReadReceipt,
} from '@/types';

export function sortConversationSummaries(
    conversations: ConversationSummary[],
): ConversationSummary[] {
    return [...conversations].sort((first, second) => {
        const firstActivity = first.activity_at ?? '';
        const secondActivity = second.activity_at ?? '';
        const activityOrder = secondActivity.localeCompare(firstActivity);

        return activityOrder !== 0 ? activityOrder : second.id - first.id;
    });
}

function compareConversationMessages(
    first: ConversationMessage,
    second: ConversationMessage,
): number {
    if (first.created_at !== second.created_at) {
        if (first.created_at === null) {
            return -1;
        }

        if (second.created_at === null) {
            return 1;
        }

        return first.created_at.localeCompare(second.created_at);
    }

    return first.id - second.id;
}

export function applyConversationMessage(
    conversations: ConversationSummary[],
    message: ConversationMessage,
    currentUserId: number,
): ConversationSummary[] {
    const target = conversations.find(
        (conversation) => conversation.id === message.conversation_id,
    );

    if (target === undefined) {
        return conversations;
    }

    const latestMessage = target.latest_message;

    if (
        latestMessage !== null &&
        compareConversationMessages(message, latestMessage) <= 0
    ) {
        return conversations;
    }

    const updated = conversations.map((conversation) =>
        conversation.id === message.conversation_id
            ? {
                  ...conversation,
                  latest_message: message,
                  activity_at: message.created_at,
                  unread_count:
                      conversation.unread_count +
                      (message.author_user_id !== currentUserId ? 1 : 0),
              }
            : conversation,
    );

    return sortConversationSummaries(updated);
}

export function conversationPreview(
    conversation: ConversationSummary,
    currentUserId: number,
    emptyLabel: string,
    currentUserPrefix: string,
): string {
    const latestMessage = conversation.latest_message;

    if (latestMessage === null) {
        return emptyLabel;
    }

    return `${latestMessage.author_user_id === currentUserId ? currentUserPrefix : ''}${latestMessage.content}`;
}

export function applyReadReceipt(
    messages: ConversationMessage[],
    receipt: MessagesReadReceipt,
    currentUserId: number,
): ConversationMessage[] {
    if (receipt.reader_user_id === currentUserId) {
        return messages;
    }

    return messages.map((message) =>
        message.conversation_id === receipt.conversation_id &&
        message.author_user_id === currentUserId &&
        message.id <= receipt.last_read_message_id
            ? { ...message, read_at: receipt.read_at }
            : message,
    );
}
