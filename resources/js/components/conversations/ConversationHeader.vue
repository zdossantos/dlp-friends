<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ConversationParticipant } from '@/types';

defineProps<{
    participant: ConversationParticipant;
    backHref?: string;
    profileHref?: string;
    blockable?: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <header
        class="flex shrink-0 items-center gap-3 border-b bg-card/95 px-4 py-3 backdrop-blur sm:px-6"
    >
        <Link
            v-if="backHref"
            :href="backHref"
            :aria-label="t('blocking.back_to_conversations')"
            class="grid size-11 shrink-0 place-items-center rounded-2xl text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <ArrowLeft class="size-5" aria-hidden="true" />
        </Link>
        <Link
            v-if="profileHref"
            :href="profileHref"
            :aria-label="
                t('blocking.profile_link', { name: participant.display_name })
            "
            class="shrink-0 rounded-2xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <AvatarPortrait
                :avatar="participant.avatar"
                class="size-11 rounded-2xl"
            />
        </Link>
        <AvatarPortrait
            v-else
            :avatar="participant.avatar"
            class="size-11 shrink-0 rounded-2xl"
        />
        <div class="min-w-0 flex-1">
            <h1 class="truncate font-semibold">
                <Link
                    v-if="profileHref"
                    :href="profileHref"
                    class="hover:underline"
                >
                    {{ participant.display_name }}
                </Link>
                <template v-else>{{ participant.display_name }}</template>
            </h1>
            <p class="text-xs text-muted-foreground">
                {{ t('blocking.private_conversation') }}
            </p>
        </div>
        <BlockMemberDialog v-if="blockable" :member-id="participant.id" />
    </header>
</template>
