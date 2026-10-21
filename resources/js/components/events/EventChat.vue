<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, onMounted, ref, watch } from 'vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
import { mergeGroupChatMessages } from '@/lib/groupChatState';
import { index as conversationsIndex } from '@/routes/conversations';
import {
    like as likeMessageRoute,
    store as storeMessage,
    unlike as unlikeMessageRoute,
} from '@/routes/events/chat/messages';
import { store as storeRead } from '@/routes/events/chat/read';
import type { MessageReactionUpdate } from '@/types';
import type {
    EventChatInfo,
    GroupChatMessage,
    EventDetail,
    EventWorkspaceContext,
    PaginatedGroupChatMessages,
} from '@/types/event';

const props = defineProps<{
    event: EventDetail;
    chat: EventChatInfo;
    messages: PaginatedGroupChatMessages;
    context: EventWorkspaceContext;
    messagesProp?: string;
}>();
const page = usePage();
const { latestPersistentNotification } = useMemberRealtimeContext();
const { t } = useTranslations();
const currentUserId = computed(() => page.props.auth.user.id);
const visibleMessages = ref<GroupChatMessage[]>([]);
const status = useConnectionStatus();
const timelineMessages = computed(() => ({ data: visibleMessages.value }));
function merge(messages: GroupChatMessage[]): void {
    visibleMessages.value = mergeGroupChatMessages(
        visibleMessages.value,
        messages,
    );
}

async function markRead(): Promise<void> {
    const id = visibleMessages.value.at(-1)?.id;

    if (id === undefined) {
        return;
    }

    const response = await fetch(storeRead(props.event.id).url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xsrfHeader(document.cookie),
        },
        body: JSON.stringify({ last_read_message_id: id }),
    });

    if (response.ok) {
        router.reload({
            only: ['auth', ...(props.messagesProp ? [props.messagesProp] : [])],
        });
    }
}

async function submit(content: string): Promise<GroupChatMessage> {
    const response = await fetch(storeMessage(props.event.id).url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xsrfHeader(document.cookie),
        },
        body: JSON.stringify({ content }),
    });
    const payload = (await response.json()) as { data?: GroupChatMessage };

    if (!response.ok || !payload.data) {
        throw new Error('Unable to send the message.');
    }

    return payload.data;
}

function sent(message: GroupChatMessage): void {
    merge([message]);
    void markRead();
}

async function reactMessage(message: {
    id: number;
    reacted_by_current_user: boolean;
}): Promise<MessageReactionUpdate> {
    const route = message.reacted_by_current_user
        ? unlikeMessageRoute([props.event.id, message.id])
        : likeMessageRoute([props.event.id, message.id]);
    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...xsrfHeader(document.cookie) },
    });
    const payload = (await response.json()) as { data?: MessageReactionUpdate };

    if (!response.ok || !payload.data) {
        throw new Error('Unable to update the reaction.');
    }

    return payload.data;
}

function updateReaction(update: MessageReactionUpdate): void {
    const message = visibleMessages.value.find(
        (item) => item.id === update.message_id,
    );

    if (message) {
        message.reaction_count = update.reaction_count;

        if (update.reactor_user_id === currentUserId.value) {
            message.reacted_by_current_user = update.reacted;
        }
    }
}

useEcho<GroupChatMessage>(
    `App.Models.User.${currentUserId.value}`,
    '.group-chat.message.sent',
    async (message) => {
        if (message.group_chat_id !== props.chat.id) {
            return;
        }

        merge([message]);
        void markRead();
    },
);

useEcho<{
    group_chat_id: number;
    message_id: number;
    reactor_user_id: number;
    reaction_count: number;
    reacted: boolean;
}>(
    `App.Models.User.${currentUserId.value}`,
    '.group-chat.message.reaction.updated',
    (update) => {
        if (update.group_chat_id !== props.chat.id) {
            return;
        }

        const message = visibleMessages.value.find(
            (item) => item.id === update.message_id,
        );

        if (message) {
            message.reaction_count = update.reaction_count;

            if (update.reactor_user_id === currentUserId.value) {
                message.reacted_by_current_user = update.reacted;
            }
        }
    },
);

watch(latestPersistentNotification, (notification) => {
    if (
        notification?.target_type === 'event_chat' &&
        notification.target_id === props.event.id
    ) {
        void markRead();
    }
});
watch(() => props.messages.data, merge, { immediate: true, deep: true });
watch(status, (next, previous) => {
    if (next === 'connected' && previous !== 'connected') {
        router.reload({
            only: props.messagesProp ? ['messages', 'chat'] : ['panel'],
            onHttpException: (response) => {
                if (props.messagesProp && response.status === 403) {
                    router.visit(
                        conversationsIndex({ query: { category: 'groups' } }),
                    );

                    return false;
                }
            },
        });
    }
});
onMounted(() => {
    void markRead();
});
</script>

<template>
    <section class="flex h-full min-h-0 flex-col" data-test="event-chat">
        <RealtimeStatus
            :unavailable="['disconnected', 'failed'].includes(status)"
            :reconnecting="['connecting', 'reconnecting'].includes(status)"
            :on-retry="() => echo().connect()"
        />
        <MessageTimeline
            :messages="timelineMessages"
            :current-user-id="currentUserId"
            timezone="Europe/Paris"
            :messages-prop="messagesProp ?? 'panel.messages'"
            reverse
            :react-message="reactMessage"
            :on-reaction-updated="updateReaction"
        />
        <MessageComposer
            :remember-key="`event-chat-draft:${chat.id}`"
            :archived="chat.isReadOnly"
            :archived-message="
                chat.readOnlyReason
                    ? t(`events.chat.read_only.${chat.readOnlyReason}`)
                    : undefined
            "
            :submit-message="submit"
            :on-sent="sent"
        />
    </section>
</template>
