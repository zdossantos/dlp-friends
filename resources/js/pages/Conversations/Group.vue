<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import AdaptiveEventPanel from '@/components/events/AdaptiveEventPanel.vue';
import EventChat from '@/components/events/EventChat.vue';
import EventInformation from '@/components/events/EventInformation.vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/conversations';
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
const infoOpen = ref(false);
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
                <button
                    type="button"
                    data-test="group-chat-info"
                    class="min-h-8 text-primary underline focus-visible:ring-2 focus-visible:ring-ring"
                    @click="infoOpen = true"
                >
                    {{ t('conversations.groups.info') }}
                </button>
            </template>
        </ConversationHeader>
        <EventChat
            :event="event"
            :chat="chat"
            :messages="messages"
            context="mine"
            messages-prop="messages"
            class="flex-1"
        />
    </main>
    <AdaptiveEventPanel
        :open="infoOpen"
        :title="t('conversations.groups.info')"
        :description="event.title"
        @update:open="infoOpen = $event"
    >
        <section data-test="group-info" class="space-y-6">
            <EventInformation :event="event" />
            <section v-if="event.participants" class="space-y-3">
                <h3 class="font-semibold">
                    {{ t('events.show.participants') }}
                </h3>
                <ul class="space-y-3">
                    <li
                        v-for="participant in event.participants"
                        :key="participant.id"
                        class="flex items-center gap-3"
                    >
                        <UserAvatar
                            :avatar="participant.avatar"
                            :display-name="participant.displayName"
                            class="size-10"
                        />
                        <span>{{
                            participant.isSelf
                                ? t('events.participants.me')
                                : (participant.displayName ??
                                  t('events.participants.blocked_user'))
                        }}</span>
                    </li>
                </ul>
            </section>
            <Button
                data-test="group-info-close"
                variant="outline"
                @click="infoOpen = false"
                >{{ t('common.actions.close') }}</Button
            >
        </section>
    </AdaptiveEventPanel>
</template>
