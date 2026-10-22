<script setup lang="ts">
import GroupChat from '@/components/groups/GroupChat.vue';
import { useTranslations } from '@/composables/useTranslations';
import { store as storeMessage, like } from '@/routes/events/chat/messages';
import { store as storeRead } from '@/routes/events/chat/read';
import type {
    EventChatInfo,
    EventDetail,
    EventWorkspaceContext,
    PaginatedGroupChatMessages,
} from '@/types/event';
defineProps<{
    event: EventDetail;
    chat: EventChatInfo;
    messages: PaginatedGroupChatMessages;
    context: EventWorkspaceContext;
    messagesProp?: string;
}>();
const { t } = useTranslations();
</script>
<template>
    <GroupChat
        data-test="event-chat"
        :chat="chat"
        :messages="messages"
        :context="{ type: 'event', id: event.id }"
        :messages-prop="messagesProp"
        :endpoints="{
            read: storeRead(event.id).url,
            send: storeMessage(event.id).url,
            reaction: (id: number) => like([event.id, id]).url,
        }"
        :archived-message="
            chat.readOnlyReason
                ? t(`events.chat.read_only.${chat.readOnlyReason}`)
                : undefined
        "
    />
</template>
