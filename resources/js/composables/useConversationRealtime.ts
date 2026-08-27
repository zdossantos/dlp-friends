import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { ConversationMessage } from '@/types';

export function useConversationRealtime(
    conversationId: number,
    onMessage: (message: ConversationMessage) => void,
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

    const connectionUnavailable = computed(() =>
        ['disconnected', 'failed'].includes(status.value),
    );
    const reconnecting = computed(() =>
        ['connecting', 'reconnecting'].includes(status.value),
    );

    function retry(): void {
        echo().connect();
    }

    return { connectionUnavailable, reconnecting, retry };
}
