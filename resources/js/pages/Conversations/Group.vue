<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import EventChat from '@/components/events/EventChat.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/conversations';
import { show as showEvent } from '@/routes/events';
import type {
    EventChatInfo,
    EventDetail,
    PaginatedEventChatMessages,
} from '@/types/event';

defineProps<{
    event: EventDetail;
    chat: EventChatInfo;
    messages: PaginatedEventChatMessages;
}>();
const { t } = useTranslations();
</script>

<template>
    <Head :title="event.title" />
    <main
        class="mx-auto flex h-full min-h-0 w-full max-w-3xl flex-col overflow-hidden rounded-3xl border bg-card"
    >
        <header class="flex shrink-0 items-center gap-3 border-b p-4">
            <Link
                :href="index({ query: { category: 'groups' } })"
                :aria-label="t('conversations.header.back')"
                data-test="group-chat-back"
                class="grid size-11 shrink-0 place-items-center rounded-full hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <div class="min-w-0">
                <h1 class="truncate font-semibold">{{ event.title }}</h1>
                <Link
                    :href="showEvent(event.id, { query: { origin: 'mine' } })"
                    class="text-sm text-primary underline"
                    >{{ t('conversations.groups.event_link') }}</Link
                >
            </div>
        </header>
        <EventChat
            :event="event"
            :chat="chat"
            :messages="messages"
            context="mine"
            messages-prop="messages"
            class="flex-1"
        />
    </main>
</template>
