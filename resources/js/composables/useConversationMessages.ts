import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { ConversationMessage } from '@/types';

export function useConversationMessages(
    initialMessages: () => ConversationMessage[],
): {
    visibleMessages: Ref<ConversationMessage[]>;
    mergeMessage: (message: ConversationMessage) => void;
    mergeMessages: (messages: ConversationMessage[]) => void;
} {
    const visibleMessages = ref<ConversationMessage[]>([]);

    function mergeMessages(messages: ConversationMessage[]): void {
        const messagesById = new Map(
            visibleMessages.value.map((message) => [message.id, message]),
        );

        for (const message of messages) {
            messagesById.set(message.id, message);
        }

        visibleMessages.value = Array.from(messagesById.values()).sort(
            (first, second) => first.id - second.id,
        );
    }

    function mergeMessage(message: ConversationMessage): void {
        mergeMessages([message]);
    }

    watch(initialMessages, mergeMessages, { deep: true, immediate: true });

    return { visibleMessages, mergeMessage, mergeMessages };
}
