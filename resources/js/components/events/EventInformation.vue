<script setup lang="ts">
import { CalendarDays, MapPin, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type { EventDetail } from '@/types/event';
defineProps<{ event: EventDetail }>();
const { t, formatDate } = useTranslations();
</script>
<template>
    <div class="space-y-6">
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
    </div>
</template>
