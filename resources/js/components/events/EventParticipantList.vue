<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { show } from '@/routes/events/participants';
import type { EventParticipant, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    eventId: number;
    context: EventWorkspaceContext;
    participants: EventParticipant[];
}>();
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
</script>

<template>
    <ul
        data-test="participant-list"
        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
        role="list"
    >
        <li
            v-for="participant in participants"
            :key="participant.id"
            data-test="participant-row"
        >
            <Link
                :href="show([eventId, participant.id], { query: origin })"
                preserve-scroll
                :data-test="`participant-link-${participant.id}`"
                class="flex min-h-16 items-center gap-3 px-4 py-3 text-card-foreground transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
            >
                <Avatar class="size-11 border border-border bg-muted">
                    <AvatarImage
                        v-if="participant.avatar"
                        :src="participant.avatar.image_url"
                        :alt="
                            participant.displayName ?? participant.avatar.name
                        "
                    />
                    <AvatarFallback>
                        {{
                            participant.displayName
                                ?.slice(0, 1)
                                .toUpperCase() ?? '?'
                        }}
                    </AvatarFallback>
                </Avatar>
                <span class="min-w-0 flex-1 truncate font-medium">{{
                    participant.displayName
                }}</span>
                <ChevronRight
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </li>
    </ul>
</template>
