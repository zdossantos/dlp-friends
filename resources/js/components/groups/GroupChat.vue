<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import RealtimeStatus from '@/components/conversations/RealtimeStatus.vue';
import MemberProfileDrawer from '@/components/members/MemberProfileDrawer.vue';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useMessagingRefresh } from '@/composables/useMessagingRefresh';
import {
    applyMessageReaction,
    mergeChatMessages,
} from '@/lib/conversationState';
import { xsrfHeader } from '@/lib/csrf';
import { resolveGroupChatDraft } from '@/lib/groupChatState';
import { index as conversationsIndex } from '@/routes/conversations';
import type { MessageReactionUpdate } from '@/types';
import type {
    GroupChatInfo,
    GroupChatMessage,
    PaginatedGroupChatMessages,
} from '@/types/groupChat';

const profileDrawer = ref<InstanceType<typeof MemberProfileDrawer> | null>(
    null,
);

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
const restoredCursor = page.props.messageUpdatesThrough;
let synchronizedThrough =
    typeof restoredCursor === 'number'
        ? restoredCursor
        : (props.messages.data.at(-1)?.id ?? 0);
let refreshing = false;
let active = true;
onBeforeUnmount(() => {
    active = false;
});
const timelineMessages = computed(() => ({ data: visibleMessages.value }));
function merge(messages: GroupChatMessage[]): void {
    visibleMessages.value = mergeChatMessages(visibleMessages.value, messages);
}

async function markRead(): Promise<void> {
    const id = synchronizedThrough;

    if (!active || document.visibilityState !== 'visible' || id === 0) {
        return;
    }

    try {
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

        if (response.ok && active) {
            router.reload({ only: ['auth'] });
        }
    } catch {
        // The next messaging refresh retries the read acknowledgement.
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
    refreshMessages();
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
    visibleMessages.value = applyMessageReaction(
        visibleMessages.value,
        update,
        currentUserId.value,
    );
}

useEcho<GroupChatMessage>(
    `App.Models.User.${currentUserId.value}`,
    '.group-chat.message.sent',
    async (message) => {
        if (message.group_chat_id !== props.chat.id) {
            return;
        }

        merge([message]);
        refreshMessages();
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

        updateReaction(update);
    },
);

watch(latestPersistentNotification, (notification) => {
    if (
        notification?.target_type === `${props.context.type}_chat` &&
        notification.target_id === props.context.id
    ) {
        refreshMessages();
    }
});
watch(() => props.messages.data, merge, { immediate: true, deep: true });
function refreshMessages(): void {
    if (!active || refreshing || document.visibilityState !== 'visible') {
        return;
    }

    refreshing = true;
    let more = false;
    const beforeRefresh = new Map(
        visibleMessages.value.map((message) => [message.id, message]),
    );

    router.reload({
        only: ['messages', 'messageUpdates', 'chat'],
        data: { messages_after: synchronizedThrough },
        preserveUrl: true,
        onBeforeUpdate: (page) => {
            const currentMessages = new Map(
                visibleMessages.value.map((message) => [message.id, message]),
            );
            function preserveNewerReaction(
                message: GroupChatMessage,
            ): GroupChatMessage {
                const current = currentMessages.get(message.id);

                return current && beforeRefresh.get(message.id) !== current
                    ? {
                          ...message,
                          reaction_count: current.reaction_count,
                          reacted_by_current_user:
                              current.reacted_by_current_user,
                      }
                    : message;
            }
            const messages = page.props.messages as PaginatedGroupChatMessages;
            const updates = (
                page.props.messageUpdates as GroupChatMessage[]
            ).map(preserveNewerReaction);
            page.props.messageUpdates = updates;
            page.props.messageUpdatesThrough = Math.max(
                synchronizedThrough,
                updates.at(-1)?.id ?? 0,
            );
            // Keep recovered history in Inertia's snapshot for back/forward.
            messages.data = mergeChatMessages(visibleMessages.value, [
                ...messages.data.map(preserveNewerReaction),
                ...updates,
            ]);
        },
        onSuccess: (page) => {
            if (!active) {
                return;
            }

            const updates = page.props.messageUpdates as GroupChatMessage[];
            merge(updates);
            synchronizedThrough = Math.max(
                synchronizedThrough,
                updates.at(-1)?.id ?? 0,
            );
            more = updates.length === 100;

            if (!more) {
                void markRead();
            }
        },
        onHttpException: (response) => {
            if (response.status === 403) {
                router.visit(
                    conversationsIndex({ query: { category: 'groups' } }),
                );

                return false;
            }
        },
        onFinish: () => {
            refreshing = false;

            if (more) {
                refreshMessages();
            }
        },
    });
}
useMessagingRefresh(refreshMessages);
</script>
<template>
    <section class="flex h-full min-h-0 flex-col" data-test="group-chat">
        <RealtimeStatus
            :unavailable="['disconnected', 'failed'].includes(status)"
            :reconnecting="['connecting', 'reconnecting'].includes(status)"
            :on-retry="() => echo().connect()"
        />
        <MessageTimeline
            @open-profile="
                (memberId, event) => profileDrawer?.openProfile(memberId, event)
            "
            :messages="timelineMessages"
            :current-user-id="currentUserId"
            timezone="Europe/Paris"
            :messages-prop="messagesProp ?? 'panel.messages'"
            reverse
            :reactions-disabled="context.type === 'trip' && chat.isReadOnly"
            :react-message="reactMessage"
            :on-reaction-updated="updateReaction"
        />
        <MemberProfileDrawer ref="profileDrawer" />
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
