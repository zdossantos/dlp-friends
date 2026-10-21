<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from '@lucide/vue';
import BackLink from '@/components/common/BackLink.vue';
import ActivityStatus from '@/components/conversations/ActivityStatus.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ConversationParticipant } from '@/types';

defineProps<{
    participant?: ConversationParticipant;
    title?: string;
    backDataTest?: string;
    backHref?: string;
    profileHref?: string;
    typing?: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <header
        class="flex shrink-0 items-center gap-3 border-b bg-card/95 px-4 py-3 backdrop-blur sm:px-6"
    >
        <BackLink
            v-if="backHref"
            :href="backHref"
            :data-test="backDataTest"
            :aria-label="t('conversations.header.back')"
            class="grid size-11 shrink-0 place-items-center rounded-2xl text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <ArrowLeft class="size-5" aria-hidden="true" />
        </BackLink>
        <Link
            v-if="profileHref && participant"
            :href="profileHref"
            :aria-label="
                t('conversations.header.profile_link', {
                    name: participant.display_name,
                })
            "
            class="shrink-0 rounded-2xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span class="block">
                <AvatarPortrait
                    :avatar="participant.avatar"
                    class="size-11 rounded-2xl"
                />
            </span>
        </Link>
        <AvatarPortrait
            v-else-if="participant"
            :avatar="participant.avatar"
            class="size-11 shrink-0 rounded-2xl"
        />
        <span
            v-else
            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-secondary text-secondary-foreground"
            ><Users class="size-6" aria-hidden="true"
        /></span>
        <div class="min-w-0 flex-1">
            <h1 class="truncate font-semibold">
                <Link
                    v-if="profileHref && participant"
                    :href="profileHref"
                    class="hover:underline"
                >
                    {{ participant.display_name }}
                </Link>
                <template v-else>{{
                    participant?.display_name ?? title
                }}</template>
            </h1>
            <ActivityStatus
                v-if="participant && (typing || participant.presence)"
                :presence="participant.presence"
                :typing="typing"
            />
            <p class="text-xs text-muted-foreground">
                <slot name="subtitle">{{
                    t('conversations.header.private_exchange')
                }}</slot>
            </p>
        </div>
        <slot />
    </header>
</template>
