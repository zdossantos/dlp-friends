<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Users } from '@lucide/vue';
import EventRegistrationActions from '@/components/events/EventRegistrationActions.vue';
import OrganizerRegistrations from '@/components/events/OrganizerRegistrations.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { cancel, edit, index } from '@/routes/events';
import type { EventDetail } from '@/types/event';
const props = defineProps<{ event: EventDetail }>();
const { t, formatDate } = useTranslations();
function cancelEvent(): void {
    if (
        window.confirm(
            `${t('events.confirmations.cancel_title')} ${t('events.confirmations.cancel_description')}`,
        )
    ) {
        router.patch(cancel(props.event.id).url);
    }
}
</script>
<template>
    <Head :title="event.title" />
    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 pb-24 sm:px-6">
        <Button as-child variant="ghost"
            ><Link :href="index()">← {{ t('events.navigation') }}</Link></Button
        >
        <article class="space-y-6 rounded-3xl border bg-card p-6 shadow-sm">
            <header class="flex items-start justify-between gap-4">
                <h1 class="text-3xl font-semibold">{{ event.title }}</h1>
                <Badge v-if="event.isCancelled" variant="destructive">{{
                    t('events.cancelled')
                }}</Badge>
            </header>
            <p class="whitespace-pre-line text-muted-foreground">
                {{ event.description }}
            </p>
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <p class="flex gap-2">
                    <CalendarDays class="size-5" />{{
                        formatDate(event.startsAt, {
                            dateStyle: 'full',
                            timeStyle: 'short',
                            timeZone: 'Europe/Paris',
                        })
                    }}
                </p>
                <p class="flex gap-2">
                    <MapPin class="size-5" />{{ event.generalLocation }}
                </p>
                <p class="flex gap-2">
                    <Users class="size-5" />{{
                        t('events.capacity', {
                            occupied: event.occupiedPlaces,
                            capacity: event.capacity,
                        })
                    }}
                </p>
            </div>
            <section
                v-if="event.detailedLocation"
                class="rounded-2xl bg-secondary p-4"
            >
                <h2 class="font-semibold">
                    {{ t('events.show.private_location') }}
                </h2>
                <p class="mt-1">{{ event.detailedLocation }}</p>
            </section>
            <p v-else class="text-sm text-muted-foreground">
                {{ t('events.show.privacy') }}
            </p>
            <EventRegistrationActions :event="event" />
            <div
                v-if="
                    event.isOrganizer && !event.isStarted && !event.isCancelled
                "
                class="flex gap-2"
            >
                <Button as-child variant="outline"
                    ><Link :href="edit(event.id)">{{
                        t('events.actions.edit')
                    }}</Link></Button
                ><Button variant="destructive" @click="cancelEvent">{{
                    t('events.actions.cancel')
                }}</Button>
            </div>
        </article>
        <section v-if="event.participants" class="space-y-3">
            <h2 class="text-xl font-semibold">
                {{ t('events.show.participants') }}
            </h2>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="participant in event.participants"
                    :key="participant.id"
                >
                    <Badge variant="secondary">{{
                        participant.displayName
                    }}</Badge>
                </li>
            </ul>
        </section>
        <section v-if="event.registrations" class="space-y-3">
            <h2 class="text-xl font-semibold">
                {{ t('events.show.requests') }}
            </h2>
            <OrganizerRegistrations :registrations="event.registrations" />
        </section>
    </main>
</template>
