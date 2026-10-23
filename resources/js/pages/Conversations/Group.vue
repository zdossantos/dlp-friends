<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import EventChat from '@/components/events/EventChat.vue';
import EventWorkspacePanel from '@/components/events/EventWorkspacePanel.vue';
import GroupChat from '@/components/groups/GroupChat.vue';
import TripInformation from '@/components/trips/TripInformation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { goBack } from '@/lib/navigationHistory';
import { index } from '@/routes/conversations';
import { show } from '@/routes/events';
import type {
    EventChatInfo,
    EventDetail,
    EventPanel,
    PaginatedGroupChatMessages,
} from '@/types/event';
import type { TripDetail } from '@/types/trip';

const props = defineProps<{
    event?: EventDetail;
    trip?: TripDetail;
    panel: EventPanel | null;
    closeHref: string;
    chat: EventChatInfo;
    messages: PaginatedGroupChatMessages;
}>();
const { t } = useTranslations();
const page = usePage();
const infoOpen = computed(() =>
    new URL(page.url, 'http://localhost').searchParams.has('info'),
);
function closeTripInfo(open: boolean) {
    if (!open) {
        goBack(props.closeHref, { preserveScroll: true });
    }
}
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
    <Head
        :title="
            event?.title ?? `${trip?.general_departure} → ${trip?.destination}`
        "
    />
    <main
        data-test="conversation-page"
        class="flex min-h-0 w-full flex-1 flex-col"
    >
        <ConversationHeader
            :title="
                event?.title ??
                `${trip?.general_departure} → ${trip?.destination}`
            "
            :back-href="index({ query: { category: 'groups' } }).url"
            back-data-test="group-chat-back"
        >
            <template #subtitle>
                <Link
                    v-if="event"
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
                <Link
                    v-else
                    :href="`${closeHref}?info=1`"
                    data-test="group-chat-info"
                    class="min-h-8 text-primary underline"
                    preserve-state
                    preserve-scroll
                    >{{ t('conversations.groups.info') }}</Link
                >
            </template>
        </ConversationHeader>
        <EventChat
            v-if="event"
            :event="event"
            :chat="chat"
            :messages="messages"
            context="mine"
            messages-prop="messages"
            class="flex-1"
        />
        <GroupChat
            v-else-if="trip"
            :chat="chat"
            :messages="messages"
            :context="{ type: 'trip', id: trip.id }"
            :endpoints="{
                read: `/trips/${trip.id}/chat/read`,
                send: `/trips/${trip.id}/chat/messages`,
                reaction: (id: number) =>
                    `/trips/${trip?.id}/chat/messages/${id}/like`,
            }"
            messages-prop="messages"
            :archived-message="
                chat.readOnlyReason
                    ? t(`trips.chat.read_only.${chat.readOnlyReason}`)
                    : undefined
            "
            class="flex-1"
        />
    </main>
    <ResponsivePanel
        v-if="trip"
        :open="infoOpen"
        @update:open="closeTripInfo"
        :title="t('trips.details')"
        :description="t('trips.description')"
    >
        <TripInformation :trip="trip" />
        <Button as-child variant="outline"
            ><Link
                :href="`/trips/${trip.id}/participants`"
                data-test="trip-participants"
                >{{ t('trips.participants') }}</Link
            ></Button
        >
    </ResponsivePanel>
    <EventWorkspacePanel
        v-if="event"
        :panel="panel"
        context="conversations"
        :close-href="closeHref"
        @update:open="updatePanel"
    />
</template>
