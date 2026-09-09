<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { show } from '@/routes/events';
import type { EventSummary } from '@/types/event';

defineProps<{ event: EventSummary }>();
const { t, formatDate } = useTranslations();
</script>

<template>
    <Card data-test="event-card" class="overflow-hidden">
        <CardHeader class="gap-2">
            <div class="flex items-start justify-between gap-3">
                <CardTitle>{{ event.title }}</CardTitle>
                <Badge v-if="event.isCancelled" variant="destructive">{{
                    t('events.cancelled')
                }}</Badge>
                <Badge v-else-if="event.registrationStatus" variant="secondary">
                    {{ t(`events.statuses.${event.registrationStatus}`) }}
                </Badge>
            </div>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
            <p class="line-clamp-3 text-muted-foreground">
                {{ event.description }}
            </p>
            <p class="flex items-center gap-2">
                <CalendarDays class="size-4" />{{
                    formatDate(event.startsAt, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                        timeZone: 'Europe/Paris',
                    })
                }}
            </p>
            <p class="flex items-center gap-2">
                <MapPin class="size-4" />{{ event.generalLocation }}
            </p>
            <p class="flex items-center gap-2">
                <Users class="size-4" />{{
                    t('events.capacity', {
                        occupied: event.occupiedPlaces,
                        capacity: event.capacity,
                    })
                }}
            </p>
        </CardContent>
        <CardFooter
            ><Button as-child variant="outline"
                ><Link :href="show(event.id)">{{
                    t('events.actions.view')
                }}</Link></Button
            ></CardFooter
        >
    </Card>
</template>
