<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { echo, useConnectionStatus, useEcho } from '@laravel/echo-vue';
import { SendHorizontal } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
import { mergeEventChatMessages } from '@/lib/eventChatState';
import { store as storeMessage } from '@/routes/events/chat/messages';
import { store as storeRead } from '@/routes/events/chat/read';
import type {
    EventChatInfo,
    EventChatMessage,
    EventDetail,
    EventWorkspaceContext,
    PaginatedEventChatMessages,
} from '@/types/event';

const props = defineProps<{
    event: EventDetail;
    chat: EventChatInfo;
    messages: PaginatedEventChatMessages;
    context: EventWorkspaceContext;
}>();
const page = usePage();
const { t } = useTranslations();
const currentUserId = computed(() => page.props.auth.user.id);
const visibleMessages = ref<EventChatMessage[]>([]);
const content = ref('');
const error = ref('');
const pending = ref(false);
const scroll = ref<HTMLElement | null>(null);
const status = useConnectionStatus();

function merge(messages: EventChatMessage[]): void {
    visibleMessages.value = mergeEventChatMessages(
        visibleMessages.value,
        messages,
    );
}

async function markRead(): Promise<void> {
    const id = visibleMessages.value.at(-1)?.id;

    if (id === undefined) {
        return;
    }

    await fetch(storeRead(props.event.id).url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xsrfHeader(document.cookie),
        },
        body: JSON.stringify({ last_read_message_id: id }),
    });
}

async function submit(): Promise<void> {
    if (pending.value || content.value.trim() === '') {
        return;
    }

    pending.value = true;
    error.value = '';

    try {
        const response = await fetch(storeMessage(props.event.id).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...xsrfHeader(document.cookie),
            },
            body: JSON.stringify({ content: content.value }),
        });
        const payload = (await response.json()) as {
            data?: EventChatMessage;
            errors?: { content?: string[] };
        };

        if (!response.ok || !payload.data) {
            error.value =
                payload.errors?.content?.[0] ?? t('events.chat.send_error');

            return;
        }

        merge([payload.data]);
        content.value = '';
        await markRead();
    } catch {
        error.value = t('events.chat.send_error');
    } finally {
        pending.value = false;
    }
}

function keydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void submit();
    }
}

useEcho<EventChatMessage>(
    `event-chat.${props.chat.id}`,
    '.event-chat.message.sent',
    async (message) => {
        merge([message]);
        await nextTick();
        scroll.value?.scrollTo({
            top: scroll.value.scrollHeight,
            behavior: 'smooth',
        });
        void markRead();
    },
);

watch(() => props.messages.data, merge, { immediate: true, deep: true });
watch(status, (next, previous) => {
    if (next === 'connected' && previous !== 'connected') {
        router.reload({ only: ['panel'] });
    }
});
onMounted(async () => {
    await nextTick();
    scroll.value?.scrollTo({ top: scroll.value.scrollHeight });
    void markRead();
});
</script>

<template>
    <section
        class="flex h-full min-h-0 flex-col bg-card"
        data-test="event-chat"
    >
        <div
            v-if="['disconnected', 'failed'].includes(status)"
            role="status"
            class="flex items-center justify-between border-b bg-destructive/10 px-4 py-2 text-sm"
        >
            <span>{{ t('events.chat.connection_unavailable') }}</span>
            <Button size="sm" variant="outline" @click="echo().connect()">{{
                t('events.chat.retry')
            }}</Button>
        </div>
        <div
            ref="scroll"
            role="log"
            aria-live="polite"
            :aria-label="t('events.chat.timeline')"
            class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6"
        >
            <Link
                v-if="messages.next_page_url"
                :href="messages.next_page_url"
                preserve-scroll
                class="mb-4 block text-center text-sm text-primary"
                >{{ t('events.chat.load_previous') }}</Link
            >
            <p
                v-if="visibleMessages.length === 0"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                {{ t('events.chat.empty') }}
            </p>
            <ol v-else class="flex flex-col gap-3">
                <li
                    v-for="message in visibleMessages"
                    :key="message.id"
                    class="flex"
                    :class="
                        message.author_user_id === currentUserId
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div class="max-w-[85%] sm:max-w-[70%]">
                        <p
                            v-if="message.author_user_id !== currentUserId"
                            class="mb-1 px-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ message.author.display_name }}
                        </p>
                        <p
                            class="rounded-3xl px-4 py-2.5 break-words whitespace-pre-wrap shadow-sm"
                            :class="
                                message.author_user_id === currentUserId
                                    ? 'rounded-br-md bg-primary text-primary-foreground'
                                    : 'rounded-bl-md border bg-background'
                            "
                        >
                            {{ message.content }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
        <footer class="shrink-0 border-t px-4 py-3 sm:px-6">
            <p
                v-if="chat.isReadOnly"
                class="text-center text-sm text-muted-foreground"
            >
                {{ t(`events.chat.read_only.${chat.readOnlyReason}`) }}
            </p>
            <form v-else class="flex items-end gap-2" @submit.prevent="submit">
                <div class="min-w-0 flex-1">
                    <label for="event-chat-content" class="sr-only">{{
                        t('events.chat.label')
                    }}</label>
                    <Textarea
                        id="event-chat-content"
                        v-model="content"
                        maxlength="2000"
                        rows="1"
                        :disabled="pending"
                        :placeholder="t('events.chat.placeholder')"
                        :aria-invalid="error !== ''"
                        class="max-h-32 min-h-11 resize-none rounded-2xl"
                        @keydown="keydown"
                    />
                    <p class="mt-1 text-right text-xs text-muted-foreground">
                        {{
                            t('events.chat.character_count', {
                                count: content.length,
                            })
                        }}
                    </p>
                    <p
                        v-if="error"
                        role="alert"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </div>
                <Button
                    type="submit"
                    size="icon"
                    class="size-11 rounded-2xl"
                    :disabled="pending || content.trim() === ''"
                    :aria-label="t('events.chat.send')"
                    ><Spinner v-if="pending" /><SendHorizontal
                        v-else
                        class="size-5"
                        aria-hidden="true"
                /></Button>
            </form>
        </footer>
    </section>
</template>
