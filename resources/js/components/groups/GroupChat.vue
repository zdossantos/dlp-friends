<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, onMounted, ref, watch } from 'vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { xsrfHeader } from '@/lib/csrf';
import {
    mergeGroupChatMessages,
    resolveGroupChatDraft,
} from '@/lib/groupChatState';
import { index as conversationsIndex } from '@/routes/conversations';
import type { MessageReactionUpdate } from '@/types';
import type {
    GroupChatInfo,
    GroupChatMessage,
    PaginatedGroupChatMessages,
} from '@/types/groupChat';

const props = defineProps<{
    chat: GroupChatInfo;
    messages: PaginatedGroupChatMessages;
    context: { type: 'event' | 'trip'; id: number };
    endpoints: { read: string; send: string; reaction: (id: number) => string };
    archivedMessage?: string;
    messagesProp?: string;
}>();
const page = usePage();
const { latestPersistentNotification } = useMemberRealtimeContext();
const currentUserId = computed(() => page.props.auth.user.id);
const draftKey = `group-chat-draft:${props.chat.id}`;
const storageKey = `${currentUserId.value}:${draftKey}`;
let storedDraft: string | null = null;

try {
    storedDraft = sessionStorage.getItem(storageKey);
} catch {
    /* Storage may be unavailable. */
}

router.remember(
    resolveGroupChatDraft(
        router.restore(draftKey),
        props.context.type === 'event'
            ? router.restore(`event-chat-draft:${props.chat.id}`)
            : undefined,
        storedDraft,
    ),
    draftKey,
);
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

    const response = await fetch(props.endpoints.read, {
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
    const response = await fetch(props.endpoints.send, {
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
    const response = await fetch(props.endpoints.reaction(message.id), {
        method: message.reacted_by_current_user ? 'DELETE' : 'POST',
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
        notification?.target_type === `${props.context.type}_chat` &&
        notification.target_id === props.context.id
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
    <section class="flex h-full min-h-0 flex-col" data-test="group-chat">
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
            :reactions-disabled="context.type === 'trip' && chat.isReadOnly"
            :react-message="reactMessage"
            :on-reaction-updated="updateReaction"
        />
        <MessageComposer
            :remember-key="draftKey"
            :persist-draft-key="storageKey"
            :archived="chat.isReadOnly"
            :archived-message="archivedMessage"
            :submit-message="submit"
            :on-sent="sent"
        />
    </section>
</template>
