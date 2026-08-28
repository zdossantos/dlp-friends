<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { useConversationListRealtime } from '@/composables/useConversationListRealtime';
import {
    applyConversationMessage,
    conversationPreview,
} from '@/lib/conversationState';
import { show as showConversation } from '@/routes/conversations';
import type { ConversationSummary } from '@/types';

const props = defineProps<{
    conversations: ConversationSummary[];
    currentUserId: number;
}>();
const visibleConversations = ref(props.conversations);

watch(
    () => props.conversations,
    (conversations) => {
        visibleConversations.value = conversations;
    },
    { deep: true },
);

useConversationListRealtime(props.currentUserId, (message) => {
    visibleConversations.value = applyConversationMessage(
        visibleConversations.value,
        message,
        props.currentUserId,
    );
});
</script>

<template>
    <Head title="Mes échanges" />

    <main
        class="mx-auto flex min-h-full w-full max-w-2xl flex-col gap-6 px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Mes échanges
            </h1>
            <p class="text-sm text-muted-foreground">
                Retrouvez ici vos conversations amicales.
            </p>
        </header>

        <section
            v-if="visibleConversations.length === 0"
            class="rounded-3xl border bg-card p-6 text-center shadow-sm"
        >
            <h2 class="font-semibold">Aucun échange pour le moment</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Vos échanges apparaîtront ici après une découverte réciproque.
            </p>
        </section>

        <section
            v-else
            aria-label="Conversations"
            class="overflow-hidden rounded-3xl border bg-card shadow-sm"
        >
            <ul role="list" class="divide-y">
                <li
                    v-for="conversation in visibleConversations"
                    :key="conversation.id"
                >
                    <Link
                        :href="showConversation(conversation.id)"
                        :data-unread="conversation.unread_count > 0"
                        class="flex min-h-20 items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                        :class="
                            conversation.unread_count > 0 ? 'bg-primary/8' : ''
                        "
                    >
                        <AvatarPortrait
                            :avatar="conversation.participant.avatar"
                            class="size-12 shrink-0 rounded-2xl"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="truncate"
                                    :class="
                                        conversation.unread_count > 0
                                            ? 'font-bold'
                                            : 'font-semibold'
                                    "
                                >
                                    {{ conversation.participant.display_name }}
                                </span>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="grid min-w-5 shrink-0 place-items-center rounded-full bg-primary px-1.5 py-0.5 text-xs font-bold text-primary-foreground"
                                    :aria-label="`${conversation.unread_count} message${conversation.unread_count > 1 ? 's' : ''} non lu${conversation.unread_count > 1 ? 's' : ''}`"
                                >
                                    {{ conversation.unread_count }}
                                </span>
                                <span
                                    v-if="conversation.archived_at"
                                    class="shrink-0 text-xs font-medium text-muted-foreground"
                                >
                                    Archivé
                                </span>
                            </span>
                            <span
                                class="mt-1 line-clamp-1 block text-sm text-muted-foreground"
                            >
                                {{
                                    conversationPreview(
                                        conversation,
                                        currentUserId,
                                    )
                                }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </main>
</template>
