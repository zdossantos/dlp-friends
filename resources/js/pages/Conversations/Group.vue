<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import GroupChat from '@/components/events/EventChat.vue';
import EventWorkspacePanel from '@/components/events/EventWorkspacePanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/conversations';
import { show } from '@/routes/events';
import type {
    EventChatInfo,
    EventDetail,
    EventPanel,
    PaginatedGroupChatMessages,
} from '@/types/event';

const props = defineProps<{
    event: EventDetail;
    panel: EventPanel | null;
    closeHref: string;
    chat: EventChatInfo;
    messages: PaginatedGroupChatMessages;
}>();
const { t } = useTranslations();
function updatePanel(open: boolean): void {
    if (!open) {
        router.visit(props.closeHref, {
            preserveState: true,
            preserveScroll: true,
            only: ['panel'],
        });
    }
}
</script>

<template>
    <Head :title="event.title" />
    <main
        data-test="conversation-page"
        class="flex min-h-0 w-full flex-1 flex-col"
    >
        <ConversationHeader
            :title="event.title"
            :back-href="index({ query: { category: 'groups' } }).url"
            back-data-test="group-chat-back"
        >
            <template #subtitle>
                <Link
                    :href="
                        show(event.id, { query: { origin: 'conversations' } })
                    "
                    preserve-state
                    preserve-scroll
                    :only="['panel']"
                    data-test="group-chat-info"
                    class="min-h-8 text-primary underline focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {{ t('conversations.groups.info') }}
                </Link>
            </template>
        </ConversationHeader>
        <GroupChat
            :event="event"
            :chat="chat"
            :messages="messages"
            context="mine"
            messages-prop="messages"
            class="flex-1"
        />
    </main>
    <EventWorkspacePanel
        :panel="panel"
        context="conversations"
        :close-href="closeHref"
        @update:open="updatePanel"
    />
</template>
