<script setup lang="ts">
import { SendHorizontal } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
import { store as storeMessage } from '@/routes/conversations/messages';
import type { ConversationMessage, ConversationStarter } from '@/types';

const props = defineProps<{
    conversationId?: number;
    archived: boolean;
    onSent: (message: ConversationMessage) => void;
    submitMessage?: (content: string) => Promise<ConversationMessage>;
    onTyping?: (content: string) => void;
    onTypingStopped?: () => void;
    starters?: ConversationStarter[];
}>();

const content = ref('');
const error = ref('');
const pending = ref(false);
const textarea = ref<{ focus: () => void } | null>(null);
const { t } = useTranslations();
const disabled = computed(
    () => props.archived || pending.value || content.value.trim() === '',
);
watch(content, (value) => props.onTyping?.(value));

async function submit(): Promise<void> {
    if (disabled.value) {
        return;
    }

    pending.value = true;
    error.value = '';

    try {
        if (props.submitMessage !== undefined) {
            const message = await props.submitMessage(content.value);
            props.onSent(message);
            content.value = '';

            return;
        }

        if (props.conversationId === undefined) {
            throw new Error('A conversation id is required.');
        }

        const response = await fetch(storeMessage(props.conversationId).url, {
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
            data?: ConversationMessage;
            errors?: { content?: string[] };
        };

        if (!response.ok || payload.data === undefined) {
            error.value =
                response.status === 422
                    ? (payload.errors?.content?.[0] ??
                      t('conversations.message.send_error'))
                    : t('conversations.message.send_error');

            return;
        }

        props.onSent(payload.data);
        content.value = '';
        props.onTypingStopped?.();
    } catch {
        error.value = t('conversations.message.send_error');
    } finally {
        pending.value = false;
        await nextTick();
        textarea.value?.focus();
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    void submit();
}

async function chooseStarter(starter: ConversationStarter): Promise<void> {
    if (content.value !== '') {
        return;
    }

    content.value = starter.text;
    await nextTick();
    textarea.value?.focus();
}
</script>

<template>
    <footer
        class="relative z-10 shrink-0 border-t bg-card/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6"
    >
        <p v-if="archived" class="text-center text-sm text-muted-foreground">
            {{ t('conversations.message.archived') }}
        </p>
        <form
            v-else
            class="mx-auto flex w-full max-w-4xl flex-col gap-3"
            @submit.prevent="submit"
        >
            <div
                v-if="starters?.length && content === ''"
                data-test="conversation-starters"
                class="w-full"
            >
                <p class="mb-2 text-sm font-medium">
                    {{ t('conversations.starters.title') }}
                </p>
                <div class="flex flex-col gap-2 pb-1">
                    <button
                        v-for="starter in starters"
                        :key="starter.id"
                        type="button"
                        class="w-full rounded-2xl border bg-background px-3 py-2 text-left text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="chooseStarter(starter)"
                    >
                        {{ starter.text }}
                    </button>
                </div>
            </div>
            <div class="flex w-full items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="message-content" class="sr-only">
                        {{ t('conversations.message.label') }}
                    </label>
                    <Textarea
                        id="message-content"
                        ref="textarea"
                        v-model="content"
                        name="content"
                        rows="1"
                        maxlength="2000"
                        aria-describedby="message-character-count message-error"
                        :aria-invalid="error !== ''"
                        :disabled="pending"
                        :placeholder="t('conversations.message.placeholder')"
                        class="max-h-32 min-h-11 resize-none rounded-2xl px-4 py-2.5 text-base"
                        @keydown="handleKeydown"
                    />
                    <p
                        id="message-character-count"
                        class="mt-1 text-right text-xs text-muted-foreground"
                    >
                        {{ content.length }} / 2 000
                    </p>
                    <p
                        v-if="error"
                        id="message-error"
                        role="alert"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </div>
                <Button
                    type="submit"
                    size="icon"
                    class="size-11 shrink-0 rounded-2xl"
                    :aria-label="t('conversations.message.send')"
                    :aria-busy="pending ? 'true' : undefined"
                    :disabled="disabled"
                >
                    <Spinner v-if="pending" class="size-5" />
                    <SendHorizontal v-else class="size-5" aria-hidden="true" />
                </Button>
            </div>
        </form>
    </footer>
</template>
