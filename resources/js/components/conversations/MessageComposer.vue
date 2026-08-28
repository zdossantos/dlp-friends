<script setup lang="ts">
import { SendHorizontal } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { store as storeMessage } from '@/routes/conversations/messages';
import type { ConversationMessage } from '@/types';

const props = defineProps<{
    conversationId: number;
    archived: boolean;
    onSent: (message: ConversationMessage) => void;
}>();

const content = ref('');
const error = ref('');
const pending = ref(false);
const textarea = ref<HTMLTextAreaElement | null>(null);
const disabled = computed(
    () => props.archived || pending.value || content.value.trim() === '',
);

async function submit(): Promise<void> {
    if (disabled.value) {
        return;
    }

    pending.value = true;
    error.value = '';

    try {
        const csrfToken = document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content');
        const response = await fetch(storeMessage(props.conversationId).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken === null || csrfToken === undefined
                    ? {}
                    : { 'X-CSRF-TOKEN': csrfToken }),
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
                      'Le message n’a pas pu être envoyé. Réessayez.')
                    : 'Le message n’a pas pu être envoyé. Réessayez.';

            return;
        }

        props.onSent(payload.data);
        content.value = '';
    } catch {
        error.value = 'Le message n’a pas pu être envoyé. Réessayez.';
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
</script>

<template>
    <footer class="shrink-0 border-t bg-card/95 px-4 py-3 sm:px-6">
        <p v-if="archived" class="text-center text-sm text-muted-foreground">
            Cet échange est archivé. L’envoi de nouveaux messages est désactivé.
        </p>
        <form v-else class="flex items-end gap-2" @submit.prevent="submit">
            <div class="min-w-0 flex-1">
                <label for="message-content" class="sr-only">Message</label>
                <textarea
                    id="message-content"
                    ref="textarea"
                    v-model="content"
                    name="content"
                    rows="1"
                    maxlength="2000"
                    aria-describedby="message-character-count message-error"
                    :aria-invalid="error !== ''"
                    :disabled="pending"
                    placeholder="Écrire un message…"
                    class="max-h-32 min-h-11 w-full resize-none rounded-2xl border bg-background px-4 py-2.5 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
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
                aria-label="Envoyer le message"
                :disabled="disabled"
            >
                <SendHorizontal class="size-5" aria-hidden="true" />
            </Button>
        </form>
    </footer>
</template>
