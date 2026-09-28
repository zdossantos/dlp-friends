<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import ConversationPattern from '@/components/conversations/ConversationPattern.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    conversationDayLabel,
    shouldShowDaySeparator,
} from '@/lib/conversationTimeline';
import { xsrfHeader } from '@/lib/csrf';
import { like, unlike } from '@/routes/conversations/messages';
import type {
    ConversationMessage,
    MessageReactionUpdate,
    PaginatedMessages,
} from '@/types';

const { locale, t } = useTranslations();

const props = defineProps<{
    messages: PaginatedMessages;
    currentUserId: number;
    participantName: string;
    timezone: string;
    conversationId: number;
    onReactionUpdated: (update: MessageReactionUpdate) => void;
}>();

const scrollContainer = ref<HTMLElement | null>(null);
const announcedMessage = ref('');
const animatedMessageId = ref<number | null>(null);
const pendingReactionIds = ref(new Set<number>());
const reactionError = ref('');
const lastTouchTap = ref<{ messageId: number; occurredAt: number } | null>(
    null,
);
const latestMessage = computed<ConversationMessage | undefined>(() =>
    props.messages.data.at(-1),
);
const lastOutgoingMessageId = computed<number | undefined>(
    () =>
        props.messages.data
            .filter((message) => message.author_user_id === props.currentUserId)
            .at(-1)?.id,
);

function showDaySeparator(index: number): boolean {
    const current = props.messages.data[index]?.created_at;

    if (current === null || current === undefined) {
        return false;
    }

    return shouldShowDaySeparator(
        props.messages.data[index - 1]?.created_at ?? null,
        current,
        locale.value,
        props.timezone,
    );
}

function dayLabel(message: ConversationMessage): string {
    if (message.created_at === null) {
        return '';
    }

    return conversationDayLabel(
        message.created_at,
        new Date(),
        locale.value,
        props.timezone,
        {
            today: t('conversations.message.today'),
            yesterday: t('conversations.message.yesterday'),
        },
    );
}

function showSender(index: number): boolean {
    return (
        index === 0 ||
        props.messages.data[index - 1]?.author_user_id !==
            props.messages.data[index]?.author_user_id ||
        showDaySeparator(index)
    );
}

function scrollToBottom(): void {
    scrollContainer.value?.scrollTo({
        top: scrollContainer.value.scrollHeight,
        behavior: 'instant',
    });
}

async function toggleReaction(message: ConversationMessage): Promise<void> {
    if (pendingReactionIds.value.has(message.id)) {
        return;
    }

    pendingReactionIds.value = new Set(pendingReactionIds.value).add(
        message.id,
    );
    reactionError.value = '';

    try {
        const route = message.reacted_by_current_user
            ? unlike([props.conversationId, message.id])
            : like([props.conversationId, message.id]);
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...xsrfHeader(document.cookie),
            },
        });
        const payload = (await response.json()) as {
            data?: MessageReactionUpdate;
        };

        if (!response.ok || payload.data === undefined) {
            throw new Error();
        }

        props.onReactionUpdated(payload.data);
    } catch {
        reactionError.value = t('conversations.reactions.error');
    } finally {
        const next = new Set(pendingReactionIds.value);
        next.delete(message.id);
        pendingReactionIds.value = next;
    }
}

function likeMessage(message: ConversationMessage): void {
    if (
        message.author_user_id !== props.currentUserId &&
        !message.reacted_by_current_user
    ) {
        void toggleReaction(message);
    }
}

function handleMessageTouchEnd(
    event: TouchEvent,
    message: ConversationMessage,
): void {
    const occurredAt = performance.now();
    const previousTap = lastTouchTap.value;
    lastTouchTap.value = { messageId: message.id, occurredAt };

    if (
        previousTap?.messageId === message.id &&
        occurredAt - previousTap.occurredAt <= 350
    ) {
        event.preventDefault();
        lastTouchTap.value = null;
        likeMessage(message);
    }
}

onMounted(() => nextTick(scrollToBottom));

watch(
    () => latestMessage.value?.id,
    async (messageId) => {
        if (messageId === undefined) {
            return;
        }

        animatedMessageId.value = messageId;

        const container = scrollContainer.value;
        const wasNearBottom =
            container !== null &&
            container.scrollHeight -
                container.scrollTop -
                container.clientHeight <=
                48;
        announcedMessage.value = t('conversations.message.received');

        if (wasNearBottom) {
            await nextTick();
            scrollToBottom();
        }
    },
);
</script>

<template>
    <section
        ref="scrollContainer"
        role="log"
        :aria-label="t('conversations.message.timeline')"
        aria-relevant="additions text"
        data-test="message-scroll"
        class="relative min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain bg-muted/25 px-3 py-4 sm:px-6"
    >
        <ConversationPattern />
        <p class="sr-only" aria-live="polite">{{ announcedMessage }}</p>
        <p v-if="reactionError" role="alert" class="sr-only">
            {{ reactionError }}
        </p>

        <p
            v-if="messages.data.length === 0"
            class="relative z-10 mx-auto mt-10 max-w-sm rounded-2xl border bg-card/90 p-5 text-center text-sm text-muted-foreground shadow-sm backdrop-blur"
        >
            {{ t('conversations.message.empty') }}
        </p>

        <InfiniteScroll
            data="messages"
            as="ol"
            only-previous
            preserve-url
            :auto-scroll="true"
            role="list"
            class="relative z-10 mx-auto flex w-full max-w-4xl flex-col gap-2"
        >
            <template #previous="{ loading }">
                <p
                    v-if="loading"
                    class="pb-3 text-center text-sm text-muted-foreground"
                    role="status"
                >
                    {{ t('conversations.message.loading_previous') }}
                </p>
            </template>

            <li
                v-for="(message, index) in messages.data"
                :key="message.id"
                :data-message-id="message.id"
                :data-sender="
                    message.author_user_id === currentUserId
                        ? 'current-user'
                        : 'participant'
                "
                class="min-w-0"
                :class="
                    message.id === animatedMessageId
                        ? 'motion-message-enter'
                        : undefined
                "
            >
                <div
                    v-if="showDaySeparator(index)"
                    data-test="conversation-day-separator"
                    class="my-3 flex items-center gap-3"
                >
                    <span class="h-px flex-1 bg-border" />
                    <time
                        :datetime="message.created_at ?? undefined"
                        class="rounded-full border bg-card/90 px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur"
                    >
                        {{ dayLabel(message) }}
                    </time>
                    <span class="h-px flex-1 bg-border" />
                </div>
                <div
                    class="flex min-w-0"
                    :class="[
                        message.author_user_id === currentUserId
                            ? 'justify-end'
                            : 'justify-start',
                    ]"
                >
                    <div
                        class="flex max-w-[88%] min-w-0 flex-col sm:max-w-[70%]"
                        :class="
                            message.author_user_id === currentUserId
                                ? 'items-end'
                                : 'items-start'
                        "
                    >
                        <p
                            v-if="showSender(index)"
                            data-test="message-sender"
                            class="mb-1 px-2 text-xs font-semibold text-muted-foreground"
                        >
                            {{
                                message.author_user_id === currentUserId
                                    ? t('conversations.message.you')
                                    : participantName
                            }}
                        </p>
                        <article
                            data-test="message-bubble"
                            class="max-w-full min-w-0 touch-manipulation px-4 py-2.5 shadow-sm"
                            :class="
                                message.author_user_id === currentUserId
                                    ? 'rounded-3xl rounded-br-md bg-primary text-primary-foreground'
                                    : 'rounded-2xl rounded-bl-sm border-l-4 border-l-secondary-foreground/35 bg-card text-card-foreground'
                            "
                            @dblclick.prevent="likeMessage(message)"
                            @touchend="
                                message.author_user_id !== currentUserId &&
                                handleMessageTouchEnd($event, message)
                            "
                        >
                            <p
                                class="[overflow-wrap:anywhere] whitespace-pre-wrap"
                            >
                                {{ message.content }}
                            </p>
                        </article>
                        <button
                            v-if="message.author_user_id !== currentUserId"
                            type="button"
                            data-test="message-like"
                            class="mt-1 min-h-8 items-center gap-1 rounded-full px-2 text-xs font-medium text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="[
                                message.reaction_count > 0
                                    ? 'inline-flex'
                                    : 'hidden sm:inline-flex',
                                message.reacted_by_current_user
                                    ? 'text-rose-600'
                                    : undefined,
                            ]"
                            :aria-pressed="message.reacted_by_current_user"
                            :aria-label="
                                message.reacted_by_current_user
                                    ? t('conversations.reactions.unlike')
                                    : t('conversations.reactions.like')
                            "
                            :disabled="pendingReactionIds.has(message.id)"
                            @click="toggleReaction(message)"
                        >
                            <Heart
                                :class="[
                                    'size-4',
                                    message.reacted_by_current_user
                                        ? 'fill-current'
                                        : undefined,
                                ]"
                                aria-hidden="true"
                            />
                            <span>{{ message.reaction_count }}</span>
                        </button>
                        <p
                            v-if="
                                message.id === lastOutgoingMessageId &&
                                message.read_at !== null
                            "
                            data-test="last-message-read"
                            class="mt-1 px-1 text-xs text-muted-foreground"
                        >
                            {{ t('conversations.message.read') }}
                        </p>
                    </div>
                </div>
            </li>
        </InfiniteScroll>
    </section>
</template>
