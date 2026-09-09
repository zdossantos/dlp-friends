import { echo } from '@laravel/echo-vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

type TypingSignal = { user_id: number; typing: boolean };

export function useConversationTyping(
    conversationId: number,
    currentUserId: number,
): {
    peerTyping: Ref<boolean>;
    signalInput: (content: string) => void;
    stop: () => void;
} {
    const peerTyping = ref(false);
    let lastStartedAt = 0;
    let outgoingTimer: ReturnType<typeof setTimeout> | undefined;
    let incomingTimer: ReturnType<typeof setTimeout> | undefined;
    const channel = echo().private(`conversation.${conversationId}`);
    const receive = (signal: TypingSignal): void => {
        if (signal.user_id === currentUserId) return;
        peerTyping.value = signal.typing;
        if (incomingTimer) clearTimeout(incomingTimer);
        if (signal.typing)
            incomingTimer = setTimeout(() => (peerTyping.value = false), 5_000);
    };
    const send = (typing: boolean): void => {
        channel.whisper('typing', { user_id: currentUserId, typing });
        if (!typing) lastStartedAt = 0;
    };
    const stop = (): void => {
        if (outgoingTimer) clearTimeout(outgoingTimer);
        send(false);
    };
    const signalInput = (content: string): void => {
        if (content.trim() === '') return stop();
        const now = Date.now();
        if (now - lastStartedAt >= 2_000) {
            send(true);
            lastStartedAt = now;
        }
        if (outgoingTimer) clearTimeout(outgoingTimer);
        outgoingTimer = setTimeout(stop, 4_000);
    };

    onMounted(() => channel.listenForWhisper('typing', receive));
    onBeforeUnmount(() => {
        stop();
        if (incomingTimer) clearTimeout(incomingTimer);
        channel.stopListeningForWhisper('typing', receive);
    });

    return { peerTyping, signalInput, stop };
}

export function useConversationListTyping(
    conversations: Array<{ id: number; participant: { id: number } }>,
): Ref<Set<number>> {
    const typingIds = ref(new Set<number>());
    const timers = new Map<number, ReturnType<typeof setTimeout>>();
    const listeners = conversations.map((conversation) => {
        const channel = echo().private(`conversation.${conversation.id}`);
        const receive = (signal: TypingSignal): void => {
            if (signal.user_id !== conversation.participant.id) return;
            const next = new Set(typingIds.value);
            signal.typing
                ? next.add(conversation.id)
                : next.delete(conversation.id);
            typingIds.value = next;
            const timer = timers.get(conversation.id);
            if (timer) clearTimeout(timer);
            if (signal.typing)
                timers.set(
                    conversation.id,
                    setTimeout(() => {
                        const expired = new Set(typingIds.value);
                        expired.delete(conversation.id);
                        typingIds.value = expired;
                    }, 5_000),
                );
        };
        return { channel, receive };
    });
    onMounted(() =>
        listeners.forEach(({ channel, receive }) =>
            channel.listenForWhisper('typing', receive),
        ),
    );
    onBeforeUnmount(() =>
        listeners.forEach(({ channel, receive }) =>
            channel.stopListeningForWhisper('typing', receive),
        ),
    );

    return typingIds;
}
