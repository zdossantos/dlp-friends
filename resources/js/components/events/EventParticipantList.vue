<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, UserRoundX } from '@lucide/vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import { show } from '@/routes/events/participants';
import type { EventParticipant, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    eventId: number;
    context: EventWorkspaceContext;
    participants: EventParticipant[];
}>();
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
const { t } = useTranslations();
</script>

<template>
    <ul data-test="participant-list" class="space-y-2" role="list">
        <li
            v-for="participant in participants"
            :key="participant.id"
            data-test="participant-row"
            class="overflow-hidden rounded-2xl border border-border bg-card"
            :class="
                participant.isBlocked ? 'bg-muted text-muted-foreground' : ''
            "
        >
            <Link
                :href="show([eventId, participant.id], { query: origin })"
                preserve-scroll
                :data-test="
                    participant.isSelf
                        ? `participant-self-${participant.id}`
                        : `participant-link-${participant.id}`
                "
                class="flex min-h-16 items-center gap-3 px-4 py-3 text-card-foreground transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
            >
                <span
                    v-if="participant.isBlocked"
                    :data-test="`participant-blocked-${participant.id}`"
                    class="grid size-11 shrink-0 place-items-center rounded-full bg-muted-foreground/15"
                >
                    <UserRoundX class="size-6" aria-hidden="true" />
                </span>
                <template v-else>
                    <UserAvatar
                        :avatar="participant.avatar"
                        :display-name="participant.displayName"
                        class="size-11 border border-border"
                    />
                </template>
                <span class="min-w-0 flex-1 truncate font-medium">{{
                    participant.isBlocked
                        ? t('events.participants.blocked_user')
                        : participant.isSelf
                          ? t('events.participants.me')
                          : participant.displayName
                }}</span>
                <ChevronRight
                    class="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </li>
    </ul>
</template>
