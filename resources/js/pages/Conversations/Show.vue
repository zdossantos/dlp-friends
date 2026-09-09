<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import { useConversationMessages } from '@/composables/useConversationMessages';
import { useConversationRealtime } from '@/composables/useConversationRealtime';
import { useConversationTyping } from '@/composables/useTypingSignals';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { xsrfHeader } from '@/lib/csrf';
import { index as conversationsIndex } from '@/routes/conversations';
import { store as storeConversationRead } from '@/routes/conversations/read';
import { show as showMember } from '@/routes/members';
import type {
    ConversationDetails,
    ConversationMessage,
    ConversationParticipant,
    PaginatedMessages,
} from '@/types';

const props = defineProps<{
    conversation: ConversationDetails;
    participant: ConversationParticipant;
    currentUserId: number;
    messages: PaginatedMessages;
}>();
const { presenceChanged } = useMemberRealtimeContext();
const participantPresence = ref(props.participant.presence);
watch(presenceChanged, (event) => {
    if (event?.user_id === props.participant.id) {
        participantPresence.value = {
            online: event.online,
            last_active_at: event.last_active_at,
        };
    }
});
const displayedParticipant = computed(() => ({
    ...props.participant,
    presence: participantPresence.value,
}));

const { visibleMessages, mergeMessage, markMessagesRead } =
    useConversationMessages(() => props.messages.data);
const {
    peerTyping,
    signalInput,
    stop: stopTyping,
} = useConversationTyping(props.conversation.id, props.currentUserId);

async function markConversationAsRead(): Promise<void> {
    await fetch(storeConversationRead(props.conversation.id).url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...xsrfHeader(document.cookie),
        },
    });
}

function handleRealtimeMessage(message: ConversationMessage): void {
    mergeMessage(message);

    if (message.author_user_id !== props.currentUserId) {
        void markConversationAsRead().catch(() => undefined);
    }
}

const { connectionUnavailable, reconnecting, retry } = useConversationRealtime(
    props.conversation.id,
    handleRealtimeMessage,
    (receipt) => markMessagesRead(receipt, props.currentUserId),
    () =>
        router.reload({
            only: ['messages'],
        }),
);
const timelineMessages = computed<PaginatedMessages>(() => ({
    ...props.messages,
    data: visibleMessages.value,
}));
</script>

<template>
    <Head :title="participant.display_name" />

    <main
        data-test="conversation-page"
        class="flex min-h-0 w-full flex-1 flex-col"
    >
        <ConversationHeader
            :participant="displayedParticipant"
            :back-href="conversationsIndex().url"
            :profile-href="
                showMember(participant.id, {
                    query: { conversation: conversation.id },
                }).url
            "
            :typing="peerTyping"
        />

        <RealtimeStatus
            :unavailable="connectionUnavailable"
            :reconnecting="reconnecting"
            :on-retry="retry"
        />

        <MessageTimeline
            :messages="timelineMessages"
            :current-user-id="currentUserId"
        />
        <MessageComposer
            :conversation-id="conversation.id"
            :archived="conversation.archived_at !== null"
            :on-sent="mergeMessage"
            :on-typing="signalInput"
            :on-typing-stopped="stopTyping"
        />
    </main>
</template>
