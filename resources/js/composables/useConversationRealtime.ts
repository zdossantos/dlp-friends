import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, watch } from 'vue';
import type { ComputedRef } from 'vue';
import type { ConversationMessage, MessagesReadReceipt } from '@/types';

export function useConversationRealtime(
    conversationId: number,
    onMessage: (message: ConversationMessage) => void,
    onMessagesRead: (receipt: MessagesReadReceipt) => void,
    onReconnect: () => void,
): {
    connectionUnavailable: ComputedRef<boolean>;
    reconnecting: ComputedRef<boolean>;
    retry: () => void;
} {
    const status = useConnectionStatus();

    useEcho<ConversationMessage>(
        `conversation.${conversationId}`,
        '.message.sent',
        onMessage,
    );
    useEcho<MessagesReadReceipt>(
        `conversation.${conversationId}`,
        '.messages.read',
        onMessagesRead,
    );

    const connectionUnavailable = computed(() =>
        ['disconnected', 'failed'].includes(status.value),
    );
    const reconnecting = computed(() =>
        ['connecting', 'reconnecting'].includes(status.value),
    );
    let hasConnected = status.value === 'connected';
    let recoveringFromOutage = false;

    watch(status, (currentStatus) => {
        if (currentStatus !== 'connected') {
            recoveringFromOutage ||= hasConnected;

            return;
        }

        if (recoveringFromOutage) {
            onReconnect();
        }

        hasConnected = true;
        recoveringFromOutage = false;
    });

    function retry(): void {
        const instance = echo();
        instance.disconnect();
        instance.connect();
    }

    return { connectionUnavailable, reconnecting, retry };
}
