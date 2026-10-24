import { echo } from '@laravel/echo-vue';
import { onBeforeUnmount, onMounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';

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
        if (signal.user_id === currentUserId) {
            return;
        }

        peerTyping.value = signal.typing;

        if (incomingTimer) {
            clearTimeout(incomingTimer);
        }

        if (signal.typing) {
            incomingTimer = setTimeout(() => (peerTyping.value = false), 5_000);
        }
    };
    const send = (typing: boolean): void => {
        channel.whisper('typing', { user_id: currentUserId, typing });

        if (!typing) {
            lastStartedAt = 0;
        }
    };
    const stop = (): void => {
        if (outgoingTimer) {
            clearTimeout(outgoingTimer);
        }

        send(false);
    };
    const signalInput = (content: string): void => {
        if (content.trim() === '') {
            return stop();
        }

        const now = Date.now();

        if (now - lastStartedAt >= 2_000) {
            send(true);
            lastStartedAt = now;
        }

        if (outgoingTimer) {
            clearTimeout(outgoingTimer);
        }

        outgoingTimer = setTimeout(stop, 4_000);
    };

    onMounted(() => channel.listenForWhisper('typing', receive));
    onBeforeUnmount(() => {
        stop();

        if (incomingTimer) {
            clearTimeout(incomingTimer);
        }

        channel.stopListeningForWhisper('typing', receive);
    });

    return { peerTyping, signalInput, stop };
}

export function useConversationListTyping(
    conversations: MaybeRefOrGetter<
        Array<{ id: number; participant: { id: number } }>
    >,
): Ref<Set<number>> {
    const typingIds = ref(new Set<number>());
    const listeners = new Map<number, { peerId: number; stop: () => void }>();

    function remove(id: number): void {
        listeners.get(id)?.stop();
        listeners.delete(id);
        typingIds.value.delete(id);
    }

    onMounted(() => {
        watch(
            () => toValue(conversations),
            (items) => {
                for (const [id, listener] of listeners) {
                    if (
                        !items.some(
                            (item) =>
                                item.id === id &&
                                item.participant.id === listener.peerId,
                        )
                    ) {
                        remove(id);
                    }
                }

                for (const conversation of items) {
                    if (listeners.has(conversation.id)) {
                        continue;
                    }

                    const channel = echo().private(
                        `conversation.${conversation.id}`,
                    );
                    let timer: ReturnType<typeof setTimeout> | undefined;
                    const receive = (signal: TypingSignal): void => {
                        if (signal.user_id !== conversation.participant.id) {
                            return;
                        }

                        clearTimeout(timer);

                        if (signal.typing) {
                            typingIds.value.add(conversation.id);
                            timer = setTimeout(
                                () => typingIds.value.delete(conversation.id),
                                5_000,
                            );
                        } else {
                            typingIds.value.delete(conversation.id);
                        }
                    };
                    channel.listenForWhisper('typing', receive);
                    listeners.set(conversation.id, {
                        peerId: conversation.participant.id,
                        stop: () => {
                            clearTimeout(timer);
                            // Other chat listeners may share this Echo channel.
                            channel.stopListeningForWhisper('typing', receive);
                        },
                    });
                }
            },
            { immediate: true, deep: true },
        );
    });
    onBeforeUnmount(() => {
        for (const id of listeners.keys()) {
            remove(id);
        }
    });

    return typingIds;
}
