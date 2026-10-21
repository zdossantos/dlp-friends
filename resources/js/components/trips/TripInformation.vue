<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDetail } from '@/types/trip';
defineProps<{ trip: TripDetail }>();
const { t, locale } = useTranslations();
</script>
<template>
    <section class="min-w-0 space-y-4 wrap-break-word">
        <template v-if="trip.transport">
            <p class="text-sm text-muted-foreground">
                {{ t(`trips.transport.${trip.transport}`) }} ·
                {{ t(`trips.direction.${trip.direction}`) }}
            </p>
            <h1 class="text-2xl font-semibold">
                {{ trip.general_departure }} → {{ trip.destination }}
            </h1>
            <p v-if="trip.departs_at">
                {{
                    new Intl.DateTimeFormat(locale, {
                        dateStyle: 'full',
                        timeStyle: 'short',
                        timeZone: 'Europe/Paris',
                    }).format(new Date(trip.departs_at))
                }}
            </p>
            <p v-if="trip.cancelled_at" class="font-semibold text-destructive">
                {{ t('trips.cancelled') }}
            </p>
            <p v-else class="font-medium">
                {{
                    trip.remaining_places === 0
                        ? t('trips.full')
                        : t('trips.places', {
                              count: trip.remaining_places ?? 0,
                          })
                }}
            </p>
            <Link
                v-if="trip.organizer"
                :href="`/trips/${trip.id}/members/${trip.organizer.id}`"
                class="inline-flex min-h-11 items-center text-primary underline underline-offset-4"
                data-test="trip-organizer"
                preserve-scroll
                >{{ trip.organizer.display_name }}</Link
            >
            <div
                v-if="trip.meeting_point"
                class="rounded-xl border border-border bg-muted p-4"
            >
                <h2 class="font-semibold">
                    {{ t('trips.fields.meeting_point') }}
                </h2>
                <p class="mt-2">{{ trip.meeting_point }}</p>
            </div>
            <p v-if="trip.cancellation_reason">
                {{ trip.cancellation_reason }}
            </p>
        </template>
        <div
            v-if="trip.registration"
            class="rounded-xl border border-border p-4"
            role="status"
        >
            <h2 class="font-medium">{{ t('trips.history') }}</h2>
            <p>{{ t(`trips.status.${trip.registration.status}`) }}</p>
            <p v-if="trip.registration.reason" class="mt-2 whitespace-pre-wrap">
                {{ trip.registration.reason }}
            </p>
        </div>
    </section>
</template>
