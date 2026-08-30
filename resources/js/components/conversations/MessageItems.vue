<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
import type { ConversationMessage } from '@/types';

const { t } = useTranslations();

defineProps<{
    messages: ConversationMessage[];
    currentUserId: number;
    lastOutgoingMessageId?: number;
}>();
</script>

<template>
    <li
        v-for="message in messages"
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
</template>
