import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import {
    applyReadReceipt,
    applyMessageReaction,
    mergeChatMessages,
} from '@/lib/conversationState';
import type {
    ConversationMessage,
    MessagesReadReceipt,
    MessageReactionUpdate,
} from '@/types';

export function useConversationMessages(
    initialMessages: () => ConversationMessage[],
): {
    visibleMessages: Ref<ConversationMessage[]>;
    mergeMessage: (message: ConversationMessage) => void;
    mergeMessages: (messages: ConversationMessage[]) => void;
    markMessagesRead: (
        receipt: MessagesReadReceipt,
        currentUserId: number,
    ) => void;
    updateReaction: (
        update: MessageReactionUpdate,
        currentUserId: number,
    ) => void;
} {
    const visibleMessages = ref<ConversationMessage[]>([]);

    function mergeMessages(messages: ConversationMessage[]): void {
        visibleMessages.value = mergeChatMessages(
            visibleMessages.value,
            messages,
        );
    }

    function updateReaction(
        update: MessageReactionUpdate,
        currentUserId: number,
    ): void {
        visibleMessages.value = applyMessageReaction(
            visibleMessages.value,
            update,
            currentUserId,
        );
    }

    function mergeMessage(message: ConversationMessage): void {
        mergeMessages([message]);
    }

    function markMessagesRead(
        receipt: MessagesReadReceipt,
        currentUserId: number,
    ): void {
        visibleMessages.value = applyReadReceipt(
            visibleMessages.value,
            receipt,
            currentUserId,
        );
    }

    watch(initialMessages, mergeMessages, { deep: true, immediate: true });

    return {
        visibleMessages,
        mergeMessage,
        mergeMessages,
        markMessagesRead,
        updateReaction,
    };
}
