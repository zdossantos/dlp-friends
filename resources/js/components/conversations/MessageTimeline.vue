<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ConversationMessage, PaginatedMessages } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    messages: PaginatedMessages;
    currentUserId: number;
}>();

const scrollContainer = ref<HTMLElement | null>(null);
const announcedMessage = ref('');
const latestMessage = computed<ConversationMessage | undefined>(() =>
    props.messages.data.at(-1),
);
const lastOutgoingMessageId = computed<number | undefined>(
    () =>
        props.messages.data
            .filter((message) => message.author_user_id === props.currentUserId)
            .at(-1)?.id,
);

function scrollToBottom(): void {
    scrollContainer.value?.scrollTo({
        top: scrollContainer.value.scrollHeight,
        behavior: 'instant',
    });
}

onMounted(() => nextTick(scrollToBottom));

watch(
    () => latestMessage.value?.id,
    async (messageId, previousMessageId) => {
        if (messageId === undefined || previousMessageId === undefined) {
            return;
        }

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
        class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6"
    >
        <p class="sr-only" aria-live="polite">{{ announcedMessage }}</p>

        <InfiniteScroll
            data="messages"
            as="ol"
            only-previous
            preserve-url
            :auto-scroll="true"
            role="list"
            class="flex flex-col gap-2"
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
                v-for="message in messages.data"
                :key="message.id"
                :data-message-id="message.id"
                class="flex"
                :class="
                    message.author_user_id === currentUserId
                        ? 'justify-end'
                        : 'justify-start'
                "
            >
                <div class="flex max-w-[85%] flex-col items-end sm:max-w-[70%]">
                    <article
                        class="w-full rounded-3xl px-4 py-2.5 shadow-sm"
                        :class="
                            message.author_user_id === currentUserId
                                ? 'rounded-br-md bg-primary text-primary-foreground'
                                : 'rounded-bl-md border bg-card text-card-foreground'
                        "
                    >
                        <p class="break-words whitespace-pre-wrap">
                            {{ message.content }}
                        </p>
                    </article>
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
            </li>
        </InfiniteScroll>
    </section>
</template>
