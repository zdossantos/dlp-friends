<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Ban, ChevronRight, MessageCircle } from '@lucide/vue';
import LikeMemberButton from '@/components/members/LikeMemberButton.vue';
import UnblockMemberButton from '@/components/members/UnblockMemberButton.vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Button } from '@/components/ui/button';
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
            <div
                v-if="participant.isBlocked"
                :data-test="`participant-blocked-${participant.id}`"
                class="flex min-h-16 flex-wrap items-center gap-3 px-4 py-3"
            >
                <span
                    class="grid size-11 shrink-0 place-items-center rounded-full bg-muted-foreground/15"
                >
                    <Ban class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1 font-medium">{{
                    t('events.participants.blocked_user')
                }}</span>
                <UnblockMemberButton
                    v-if="participant.canUnblock"
                    :member-id="participant.id"
                    :return-href="$page.url"
                    :data-test="`participant-unblock-${participant.id}`"
                    class="min-h-11"
                />
            </div>
            <div
                v-else
                :data-test="
                    participant.isSelf
                        ? `participant-self-${participant.id}`
                        : undefined
                "
                class="flex min-h-16 flex-wrap items-center gap-2 p-2"
            >
                <Link
                    :href="show([eventId, participant.id], { query: origin })"
                    preserve-scroll
                    :data-test="`participant-link-${participant.id}`"
                    class="flex min-w-44 flex-1 items-center gap-3 rounded-xl px-2 py-1.5 text-card-foreground transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                >
                    <UserAvatar
                        :avatar="participant.avatar"
                        :display-name="participant.displayName"
                        class="size-11 border border-border"
                    />
                    <span class="min-w-0 flex-1 truncate font-medium">{{
                        participant.isSelf
                            ? t('events.participants.me')
                            : participant.displayName
                    }}</span>
                    <ChevronRight
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>
                <LikeMemberButton
                    v-if="participant.canLike"
                    :member-id="participant.id"
                    :return-href="$page.url"
                    :data-test="`participant-like-${participant.id}`"
                    class="min-h-11"
                />
                <Button
                    v-else-if="participant.conversationHref"
                    as-child
                    variant="secondary"
                    class="min-h-11"
                >
                    <Link
                        :href="participant.conversationHref"
                        :data-test="`participant-discuss-${participant.id}`"
                    >
                        <MessageCircle class="size-4" aria-hidden="true" />
                        {{ t('events.participants.discuss') }}
                    </Link>
                </Button>
            </div>
        </li>
    </ul>
</template>
