<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { ArrowDown, Heart } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ConversationPattern from '@/components/conversations/ConversationPattern.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    conversationDayLabel,
    shouldShowDaySeparator,
} from '@/lib/conversationTimeline';
import { xsrfHeader } from '@/lib/csrf';
import { like, unlike } from '@/routes/conversations/messages';
import type { MessageReactionUpdate, PaginatedMessages } from '@/types';
import type { GroupChatMessage } from '@/types/event';

type TimelineMessage = PaginatedMessages['data'][number] | GroupChatMessage;

const { locale, t } = useTranslations();

const props = defineProps<{
    messages: { data: TimelineMessage[] };
    messagesProp?: string;
    reverse?: boolean;
    currentUserId: number;
    participantName?: string;
    timezone: string;
    conversationId?: number;
    reactionsDisabled?: boolean;
    reactMessage?: (message: TimelineMessage) => Promise<MessageReactionUpdate>;
    onReactionUpdated: (update: MessageReactionUpdate) => void;
}>();

const emit = defineEmits<{
    'open-profile': [memberId: number, event: MouseEvent];
}>();

const loadingSlot = computed(() => (props.reverse ? 'next' : 'previous'));
const scrollContainer = ref<HTMLElement | null>(null);
const awayFromBottom = ref(false);
let followLatest = true;
let resizeObserver: ResizeObserver | undefined;
function handleScroll(): void {
    const container = scrollContainer.value;

    if (!container) {
        return;
    }

    followLatest =
        container.scrollHeight - container.scrollTop - container.clientHeight <=
        48;
    awayFromBottom.value = !followLatest;
}
onMounted(() => {
    const container = scrollContainer.value;

    if (!container) {
        return;
    }

    resizeObserver = new ResizeObserver(() => {
        if (followLatest) {
            scrollToBottom();
        }
    });
    resizeObserver.observe(container);
    const items = container.querySelector('ol');

    if (items) {
        resizeObserver.observe(items);
    }
});
onUnmounted(() => resizeObserver?.disconnect());
let messageItems: HTMLElement | null = null;
function getMessageItems(): HTMLElement | null {
    // Inertia may finish its debounced history callback after this view unmounts.
    messageItems = scrollContainer.value?.querySelector('ol') ?? messageItems;

    return messageItems;
}
// Inertia anchors a list item against the window. In this nested scroller,
// that item can be clipped by the header, and its date/sender heading can
// disappear when older messages arrive. Anchor the visible bubble instead.
let readingAnchor: { element: HTMLElement; top: number } | null = null;
const historyReloadOptions = {
    onBeforeUpdate: () => {
        const container = scrollContainer.value;
        const bounds = container?.getBoundingClientRect();
        const element =
            bounds && container
                ? Array.from(
                      container.querySelectorAll<HTMLElement>(
                          '[data-message-id] article',
                      ),
                  ).find((bubble) => {
                      const rect = bubble.getBoundingClientRect();

                      return (
                          rect.bottom > bounds.top && rect.top < bounds.bottom
                      );
                  })
                : undefined;

        readingAnchor = element
            ? { element, top: element.getBoundingClientRect().top }
            : null;
    },
    onSuccess: () => {
        const anchor = readingAnchor;
        // Run after Vue and Inertia's item restoration, within the same frame.
        void nextTick(() =>
            requestAnimationFrame(() => {
                const container = scrollContainer.value;

                if (!container || !anchor?.element.isConnected) {
                    return;
                }

                const offset =
                    anchor.element.getBoundingClientRect().top - anchor.top;

                if (Math.abs(offset) > 1) {
                    container.scrollBy({ top: offset, behavior: 'instant' });
                }
            }),
        );
    },
};
const announcedMessage = ref('');
const animatedMessageId = ref<number | null>(null);
const pendingReactionIds = ref(new Set<number>());
const reactionError = ref('');
const lastTouchTap = ref<{ messageId: number; occurredAt: number } | null>(
    null,
);
const latestMessage = computed<TimelineMessage | undefined>(() =>
    props.messages.data.at(-1),
);
const lastOutgoingMessageId = computed<number | undefined>(
    () =>
        props.messages.data
            .filter(
                (message) =>
                    !isSystemMessage(message) &&
                    message.author_user_id === props.currentUserId,
            )
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

function dayLabel(message: TimelineMessage): string {
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

function isSystemMessage(
    message: TimelineMessage,
): message is GroupChatMessage & {
    system_type: NonNullable<GroupChatMessage['system_type']>;
} {
    return 'system_type' in message && message.system_type != null;
}

function systemMessageText(message: GroupChatMessage): string {
    return t(`events.chat.system.${message.system_type}`, {
        name: message.author.display_name,
    });
}

function showSender(index: number): boolean {
    return (
        index === 0 ||
        isSystemMessage(props.messages.data[index - 1]) ||
        props.messages.data[index - 1]?.author_user_id !==
            props.messages.data[index]?.author_user_id ||
        showDaySeparator(index)
    );
}

function scrollToBottom(): void {
    followLatest = true;
    awayFromBottom.value = false;
    scrollContainer.value?.scrollTo({
        top: scrollContainer.value.scrollHeight,
        behavior: 'instant',
    });
}

async function toggleReaction(message: TimelineMessage): Promise<void> {
    if (
        props.reactionsDisabled ||
        isSystemMessage(message) ||
        pendingReactionIds.value.has(message.id)
    ) {
        return;
    }

    pendingReactionIds.value = new Set(pendingReactionIds.value).add(
        message.id,
    );
    reactionError.value = '';

    try {
        if (props.reactMessage) {
            props.onReactionUpdated(await props.reactMessage(message));

            return;
        }

        if (props.conversationId === undefined) {
            return;
        }

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

function likeMessage(message: TimelineMessage): void {
    if (
        message.author_user_id !== props.currentUserId &&
        !message.reacted_by_current_user
    ) {
        void toggleReaction(message);
    }
}

function handleMessageTouchEnd(
    event: TouchEvent,
    message: TimelineMessage,
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

watch(
    () => latestMessage.value?.id,
    async (messageId) => {
        if (messageId === undefined) {
            return;
        }

        animatedMessageId.value = messageId;

        if (latestMessage.value?.author_user_id !== props.currentUserId) {
            announcedMessage.value =
                latestMessage.value && isSystemMessage(latestMessage.value)
                    ? systemMessageText(latestMessage.value)
                    : t('conversations.message.received');
        }

        if (
            followLatest ||
            latestMessage.value?.author_user_id === props.currentUserId
        ) {
            await nextTick();
            scrollToBottom();
        }
    },
);
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col">
        <section
            ref="scrollContainer"
            @scroll.passive="handleScroll"
            role="log"
            :aria-label="t('conversations.message.timeline')"
            aria-relevant="additions text"
            data-test="message-scroll"
            class="relative min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain bg-muted/25 px-3 py-4 [overflow-anchor:none] sm:px-6"
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
                :data="messagesProp ?? 'messages'"
                :items-element="getMessageItems"
                :reverse="reverse"
                as="ol"
                :only-previous="!reverse"
                :only-next="reverse"
                preserve-url
                :buffer="400"
                :params="historyReloadOptions"
                :auto-scroll="true"
                role="list"
                class="relative z-10 mx-auto flex w-full max-w-4xl flex-col gap-2"
            >
                <template #[loadingSlot]="{ loading }">
                    <p
                        v-if="loading"
                        class="absolute inset-x-0 top-0 text-center text-sm text-muted-foreground"
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
                    <p
                        v-if="isSystemMessage(message)"
                        data-test="event-chat-system-message"
                        class="mx-auto my-2 w-fit max-w-full rounded-xl bg-muted/90 px-3 py-1.5 text-center text-xs [overflow-wrap:anywhere] text-muted-foreground"
                    >
                        {{ systemMessageText(message) }}
                    </p>
                    <div
                        v-else
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
                                <template
                                    v-if="
                                        message.author_user_id === currentUserId
                                    "
                                    >{{
                                        t('conversations.message.you')
                                    }}</template
                                >
                                <button
                                    v-else
                                    type="button"
                                    :data-profile-id="message.author_user_id"
                                    :aria-label="
                                        t('conversations.header.profile_link', {
                                            name:
                                                'author' in message
                                                    ? message.author
                                                          .display_name
                                                    : (participantName ?? ''),
                                        })
                                    "
                                    class="inline-flex min-h-11 items-center rounded-sm text-left hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="
                                        emit(
                                            'open-profile',
                                            message.author_user_id,
                                            $event,
                                        )
                                    "
                                >
                                    {{
                                        'author' in message
                                            ? message.author.display_name
                                            : participantName
                                    }}
                                </button>
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
                                class="inline-flex min-h-8 items-center gap-1 rounded-full text-xs font-medium text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :class="[
                                    message.reaction_count > 0
                                        ? 'mt-1 px-2'
                                        : 'sr-only focus:not-sr-only focus:mt-1 focus:px-2 sm:not-sr-only sm:mt-1 sm:px-2',
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
                                :disabled="
                                    reactionsDisabled ||
                                    pendingReactionIds.has(message.id)
                                "
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
                                    'read_at' in message &&
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
        <button
            v-if="awayFromBottom"
            type="button"
            data-test="latest-messages"
            class="absolute bottom-3 left-1/2 z-20 flex min-h-11 -translate-x-1/2 items-center gap-2 rounded-full border bg-card px-4 py-2 text-sm font-medium shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @click="scrollToBottom"
        >
            <ArrowDown class="size-4" aria-hidden="true" />
            {{ t('conversations.message.latest') }}
        </button>
    </div>
</template>
