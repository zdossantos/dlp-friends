<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { EventParticipant } from '@/types/event';

const props = defineProps<{ participants: EventParticipant[]; href: string }>();
const { t } = useTranslations();
const visible = computed(() => props.participants.slice(0, 3));
const remaining = computed(() => Math.max(0, props.participants.length - 3));
</script>

<template>
    <Link
        :href="href"
        preserve-scroll
        data-test="participant-stack-trigger"
        class="inline-flex min-h-11 items-center rounded-full pr-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
        :aria-label="
            t('events.participants.open', { count: participants.length })
        "
    >
        <UserAvatar
            v-for="(participant, index) in visible"
            :key="participant.id"
            :avatar="participant.avatar"
            :display-name="participant.displayName"
            data-test="participant-stack-avatar"
            class="size-12 border-2 border-card shadow-sm"
            :class="index > 0 ? '-ml-3' : ''"
        />
        <span
            v-if="remaining > 0"
            class="-ml-3 flex size-12 items-center justify-center rounded-full border-2 border-card bg-muted font-semibold text-muted-foreground shadow-sm"
            >+{{ remaining }}</span
        >
    </Link>
</template>
