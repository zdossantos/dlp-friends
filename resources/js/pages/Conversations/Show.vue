<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { useConversationMessages } from '@/composables/useConversationMessages';
import { useConversationRealtime } from '@/composables/useConversationRealtime';
import { index as conversationsIndex } from '@/routes/conversations';
import { store as storeConversationRead } from '@/routes/conversations/read';
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

const { visibleMessages, mergeMessage, markMessagesRead } =
    useConversationMessages(() => props.messages.data);

async function markConversationAsRead(): Promise<void> {
    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    await fetch(storeConversationRead(props.conversation.id).url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(csrfToken === null || csrfToken === undefined
                ? {}
                : { 'X-CSRF-TOKEN': csrfToken }),
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
        <header
            class="flex shrink-0 items-center gap-3 border-b bg-card/95 px-4 py-3 backdrop-blur sm:px-6"
        >
            <Link
                :href="conversationsIndex()"
                aria-label="Retour aux échanges"
                class="grid size-11 shrink-0 place-items-center rounded-2xl text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <AvatarPortrait
                :avatar="participant.avatar"
                class="size-11 shrink-0 rounded-2xl"
            />
            <div class="min-w-0">
                <h1 class="truncate font-semibold">
                    {{ participant.display_name }}
                </h1>
                <p class="text-xs text-muted-foreground">Échange privé</p>
            </div>
        </header>

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
        />
    </main>
</template>
