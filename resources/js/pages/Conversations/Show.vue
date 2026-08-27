<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import MessageTimeline from '@/components/conversations/MessageTimeline.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { index as conversationsIndex } from '@/routes/conversations';
import type {
    ConversationDetails,
    ConversationParticipant,
    PaginatedMessages,
} from '@/types';

defineProps<{
    conversation: ConversationDetails;
    participant: ConversationParticipant;
    currentUserId: number;
    messages: PaginatedMessages;
}>();
</script>

<template>
    <Head :title="participant.display_name" />

    <main class="flex h-full min-h-0 w-full flex-1 flex-col">
        <header
            class="flex shrink-0 items-center gap-3 border-b bg-card/95 px-4 py-3 backdrop-blur sm:px-6"
        >
            <Link
                :href="conversationsIndex()"
                aria-label="Retour aux échanges"
                class="grid size-11 shrink-0 place-items-center rounded-2xl text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <AvatarPortrait
                :avatar="participant.avatar"
                class="size-11 shrink-0 rounded-2xl"
            />
            <div class="min-w-0">
                <h1 class="truncate font-semibold">
                    {{ participant.display_name }}
                </h1>
                <p class="text-xs text-muted-foreground">Échange privé</p>
            </div>
        </header>

        <MessageTimeline
            :messages="messages"
            :current-user-id="currentUserId"
        />
    </main>
</template>
