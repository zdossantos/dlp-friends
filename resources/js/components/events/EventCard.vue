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
import type { EventSummary, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    event: EventSummary;
    context: EventWorkspaceContext;
    role?: 'organizer' | 'participant';
}>();
const { t, formatDate } = useTranslations();
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
</script>

<template>
    <Card data-test="event-card" class="overflow-hidden">
        <CardHeader class="gap-2">
            <div class="flex items-start justify-between gap-3">
                <CardTitle class="text-card-foreground">{{
                    event.title
                }}</CardTitle>
                <div class="flex flex-wrap justify-end gap-2">
                    <Badge
                        v-if="role"
                        :data-test="`event-role-${role}`"
                        variant="outline"
                        class="border-border bg-background text-foreground"
                    >
                        {{ t(`events.roles.${role}`) }}
                    </Badge>
                    <Badge v-if="event.isCancelled" variant="destructive">
                        {{ t('events.cancelled') }}
                    </Badge>
                    <Badge
                        v-else-if="event.registrationStatus"
                        variant="secondary"
                    >
                        {{ t(`events.statuses.${event.registrationStatus}`) }}
                    </Badge>
                </div>
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
                ><Link
                    :href="show(event.id, { query: origin })"
                    :data-test="`event-link-${event.id}`"
                    >{{ t('events.actions.view') }}</Link
                ></Button
            ></CardFooter
        >
    </Card>
</template>
