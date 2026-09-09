<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import EventConfirmationDialog from '@/components/events/EventConfirmationDialog.vue';
import EventParticipantStack from '@/components/events/EventParticipantStack.vue';
import EventRegistrationActions from '@/components/events/EventRegistrationActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { cancel, edit } from '@/routes/events';
import { index as participantsIndex } from '@/routes/events/participants';
import { index as registrationsIndex } from '@/routes/events/registrations';
import type { EventDetail, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    event: EventDetail;
    context: EventWorkspaceContext;
}>();
const { t, formatDate } = useTranslations();
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
const cancelling = ref(false);
const pendingCount = computed(
    () =>
        props.event.registrations?.filter(
            (registration) => registration.status === 'pending',
        ).length ?? 0,
);

function cancelEvent(): void {
    cancelling.value = true;
    router.patch(
        cancel(props.event.id, { query: origin }).url,
        {},
        {
            onFinish: () => (cancelling.value = false),
        },
    );
}
</script>

<template>
    <article data-test="event-detail" class="space-y-6 pt-2">
        <header class="flex items-start justify-between gap-4">
            <h2
                data-test="event-detail-title"
                class="text-2xl font-semibold text-foreground"
            >
                {{ event.title }}
            </h2>
            <Badge v-if="event.isCancelled" variant="destructive">
                {{ t('events.cancelled') }}
            </Badge>
        </header>
        <p
            data-test="event-detail-description"
            class="whitespace-pre-line text-muted-foreground"
        >
            {{ event.description }}
        </p>
        <div class="grid gap-3 text-sm sm:grid-cols-2">
            <p class="flex items-start gap-2 text-foreground">
                <CalendarDays class="mt-0.5 size-5 shrink-0" />
                {{
                    formatDate(event.startsAt, {
                        dateStyle: 'full',
                        timeStyle: 'short',
                        timeZone: 'Europe/Paris',
                    })
                }}
            </p>
            <p class="flex items-start gap-2 text-foreground">
                <MapPin class="mt-0.5 size-5 shrink-0" />
                {{ event.generalLocation }}
            </p>
            <p class="flex items-start gap-2 text-foreground">
                <Users class="mt-0.5 size-5 shrink-0" />
                {{
                    t('events.capacity', {
                        occupied: event.occupiedPlaces,
                        capacity: event.capacity,
                    })
                }}
            </p>
        </div>
        <section
            v-if="event.detailedLocation"
            data-test="event-private-location"
            class="rounded-2xl bg-secondary p-4 text-secondary-foreground"
        >
            <h3 class="font-semibold">
                {{ t('events.show.private_location') }}
            </h3>
            <p class="mt-1">{{ event.detailedLocation }}</p>
        </section>
        <p v-else class="text-sm text-muted-foreground">
            {{ t('events.show.privacy') }}
        </p>
        <EventRegistrationActions :event="event" :context="context" />
        <div
            v-if="event.isOrganizer && !event.isStarted && !event.isCancelled"
            class="flex flex-wrap gap-2"
        >
            <Button as-child variant="outline">
                <Link
                    :href="edit(event.id, { query: origin })"
                    data-test="event-edit"
                >
                    {{ t('events.actions.edit') }}
                </Link>
            </Button>
            <Button v-if="event.registrations" as-child variant="outline">
                <Link
                    :href="registrationsIndex(event.id, { query: origin })"
                    data-test="event-registrations"
                >
                    {{
                        t('events.actions.manage_requests', {
                            count: pendingCount,
                        })
                    }}
                </Link>
            </Button>
            <EventConfirmationDialog
                :title="t('events.confirmations.cancel_title')"
                :description="t('events.confirmations.cancel_description')"
                :confirm-label="t('events.actions.cancel')"
                :busy="cancelling"
                destructive
                @confirm="cancelEvent"
            >
                <Button variant="destructive" :disabled="cancelling">
                    {{ t('events.actions.cancel') }}
                </Button>
            </EventConfirmationDialog>
        </div>
        <section v-if="event.participants" class="space-y-3">
            <h3 class="text-lg font-semibold">
                {{ t('events.show.participants') }}
            </h3>
            <EventParticipantStack
                :participants="event.participants"
                :href="participantsIndex(event.id, { query: origin }).url"
            />
        </section>
    </article>
</template>
