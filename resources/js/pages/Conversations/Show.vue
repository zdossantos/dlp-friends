<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import ReportConversationDialog from '@/components/members/ReportConversationDialog.vue';
import { useConversationMessages } from '@/composables/useConversationMessages';
import { useConversationRealtime } from '@/composables/useConversationRealtime';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useMessagingRefresh } from '@/composables/useMessagingRefresh';
import { useConversationTyping } from '@/composables/useTypingSignals';
import { xsrfHeader } from '@/lib/csrf';
import { mergeGroupChatMessages } from '@/lib/groupChatState';
import { shouldAcknowledgePersistentNotification } from '@/lib/memberNotifications';
import { index as conversationsIndex } from '@/routes/conversations';
import { store as storeConversationRead } from '@/routes/conversations/read';
import { show as showMember } from '@/routes/members';
import type {
    ConversationDetails,
    ConversationMessage,
    ConversationStarter,
    MessageReactionUpdate,
    ConversationParticipant,
    PaginatedMessages,
} from '@/types';

const props = defineProps<{
    conversation: ConversationDetails;
    participant: ConversationParticipant;
    currentUserId: number;
    timezone: string;
    messages: PaginatedMessages;
    messageUpdates?: ConversationMessage[];
    conversationStarters: ConversationStarter[];
    canBlock: boolean;
}>();
const { latestPersistentNotification, latestMessage, presenceChanged } =
    useMemberRealtimeContext();
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

const {
    visibleMessages,
    mergeMessage,
    mergeMessages,
    markMessagesRead,
    updateReaction,
} = useConversationMessages(() => props.messages.data);
// Live events may arrive out of order. Only a complete server catch-up can
// advance the recovery cursor past messages that were missed during an outage.
let synchronizedThrough = props.messages.data.at(-1)?.id ?? 0;
watch(
    () => props.messageUpdates,
    (messages) => {
        if (messages) {
            mergeMessages(messages);
            synchronizedThrough = Math.max(
                synchronizedThrough,
                messages.at(-1)?.id ?? 0,
            );
        }
    },
);
function refreshMessages(): void {
    const beforeRefresh = new Map(
        visibleMessages.value.map((message) => [message.id, message]),
    );
    // A slow snapshot must not undo reactions or receipts received while it
    // was in flight. The next refresh can reconcile their server state again.
    function preserveNewerState(
        message: ConversationMessage,
    ): ConversationMessage {
        const before = beforeRefresh.get(message.id);
        const current = visibleMessages.value.find(
            (item) => item.id === message.id,
        );

        if (!current) {
            return message;
        }

        return {
            ...message,
            // Each live update replaces the message object, even when a like
            // followed by an unlike ends with the same values as before.
            ...(before !== current
                ? {
                      reaction_count: current.reaction_count,
                      reacted_by_current_user: current.reacted_by_current_user,
                  }
                : {}),
            read_at: current.read_at ?? message.read_at,
        };
    }

    router.reload({
        only: ['messages', 'messageUpdates', 'auth'],
        preserveUrl: true,
        data: { messages_after: synchronizedThrough },
        onBeforeUpdate: (page) => {
            const messages = page.props.messages as PaginatedMessages;
            page.props.messageUpdates = (
                (page.props.messageUpdates as
                    ConversationMessage[] | undefined) ?? []
            ).map(preserveNewerState);
            // Keep the complete loaded timeline in Inertia's existing snapshot
            // so back/forward restores messages recovered while this page was open.
            messages.data = mergeGroupChatMessages(visibleMessages.value, [
                ...messages.data.map(preserveNewerState),
                ...(page.props.messageUpdates as ConversationMessage[]),
            ]);
        },
    });
}
useMessagingRefresh(refreshMessages);
watch(latestMessage, (message) => {
    if (message?.conversation_id === props.conversation.id) {
        handleRealtimeMessage(message);
    }
});
const {
    peerTyping,
    signalInput,
    stop: stopTyping,
} = useConversationTyping(props.conversation.id, props.currentUserId);

async function markConversationAsRead(): Promise<void> {
    const response = await fetch(
        storeConversationRead(props.conversation.id).url,
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...xsrfHeader(document.cookie),
            },
        },
    );

    if (!response.ok) {
        throw new Error('Unable to mark the conversation as read.');
    }
}

watch(latestPersistentNotification, (notification) => {
    if (
        document.visibilityState !== 'visible' ||
        !notification ||
        !shouldAcknowledgePersistentNotification(
            notification,
            window.location.pathname,
        )
    ) {
        return;
    }

    void markConversationAsRead()
        .then(() => router.reload({ only: ['auth'] }))
        .catch(() => undefined);
});

function handleRealtimeMessage(message: ConversationMessage): void {
    mergeMessage(message);

    if (
        document.visibilityState === 'visible' &&
        message.author_user_id !== props.currentUserId
    ) {
        void markConversationAsRead().catch(() => undefined);
    }
}

const { connectionUnavailable, reconnecting, retry } = useConversationRealtime(
    props.conversation.id,
    handleRealtimeMessage,
    (receipt) => markMessagesRead(receipt, props.currentUserId),
    (update) => updateReaction(update, props.currentUserId),
    refreshMessages,
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
            ><ReportConversationDialog
                :conversation-id="conversation.id"
                :can-block="canBlock"
        /></ConversationHeader>

        <RealtimeStatus
            :unavailable="connectionUnavailable"
            :reconnecting="reconnecting"
            :on-retry="retry"
        />

        <MessageTimeline
            :messages="timelineMessages"
            :current-user-id="currentUserId"
            :participant-name="participant.display_name"
            :timezone="timezone"
            :conversation-id="conversation.id"
            :on-reaction-updated="
                (update: MessageReactionUpdate) =>
                    updateReaction(update, currentUserId)
            "
        />
        <MessageComposer
            :conversation-id="conversation.id"
            :archived="conversation.archived_at !== null"
            :on-sent="mergeMessage"
            :on-typing="signalInput"
            :on-typing-stopped="stopTyping"
            :starters="visibleMessages.length === 0 ? conversationStarters : []"
        />
    </main>
</template>
