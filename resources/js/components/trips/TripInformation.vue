<script setup lang="ts">
import {
    Car,
    TrainFront,
    BusFront,
    Clock3,
    Users,
    LockKeyhole,
} from '@lucide/vue';
import TripMemberLink from '@/components/trips/TripMemberLink.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDetail } from '@/types/trip';
const props = defineProps<{ trip: TripDetail; compact?: boolean }>();
const { t, formatDate } = useTranslations();
function formatSchedule(value: string): string {
    return formatDate(value, {
        dateStyle: props.compact ? 'medium' : 'long',
        timeStyle: 'short',
        timeZone: 'Europe/Paris',
    });
}
function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return t(
        hours === 0
            ? 'trips.duration_minutes'
            : remaining === 0
              ? 'trips.duration_hours'
              : 'trips.duration_hours_minutes',
        { hours, minutes: remaining },
    );
}
</script>
<template>
    <section
        class="min-w-0 wrap-break-word"
        :class="compact ? 'space-y-3' : 'space-y-5'"
    >
        <template v-if="trip.transport">
            <div
                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
            >
                <component
                    :is="
                        trip.transport === 'car'
                            ? Car
                            : trip.transport === 'bus'
                              ? BusFront
                              : TrainFront
                    "
                    class="size-4"
                    aria-hidden="true"
                />
                {{ t(`trips.transport.${trip.transport}`) }}
                <span aria-hidden="true">·</span
                >{{ t(`trips.direction.${trip.direction}`) }}
            </div>
            <div
                class="grid min-w-0 grid-cols-[1rem_minmax(0,1fr)] gap-x-3 gap-y-4"
            >
                <div class="relative flex justify-center" aria-hidden="true">
                    <svg
                        class="route-line pointer-events-none absolute top-3 left-1/2 h-[calc(100%+1rem)] w-0.5 -translate-x-1/2 text-primary"
                        viewBox="0 0 2 100"
                        preserveAspectRatio="none"
                        fill="none"
                        :class="{ 'route-animated': !compact }"
                    >
                        <path
                            d="M1 0V100"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-dasharray="3 5"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>
                    <span
                        data-test="trip-route-marker"
                        class="route-marker relative mt-1.5 size-3 shrink-0 border-2 border-primary bg-card"
                    />
                </div>
                <div class="col-start-2 min-w-0">
                    <p class="text-xs text-muted-foreground">
                        {{ t('trips.departure') }}
                    </p>
                    <component
                        :is="compact ? 'h2' : 'h1'"
                        class="mt-1 font-semibold text-balance wrap-break-word"
                        :class="compact ? 'text-lg' : 'text-2xl sm:text-3xl'"
                        >{{ trip.general_departure }}</component
                    >
                    <time
                        v-if="trip.departs_at"
                        :datetime="trip.departs_at"
                        :aria-label="t('trips.schedule')"
                        class="mt-1 block text-sm text-muted-foreground"
                        >{{ formatSchedule(trip.departs_at) }}</time
                    >
                </div>
                <div class="flex justify-center" aria-hidden="true">
                    <span
                        data-test="trip-route-marker"
                        class="route-marker mt-1.5 size-3 shrink-0 bg-primary"
                    />
                </div>
                <div class="col-start-2 min-w-0">
                    <p class="text-xs text-muted-foreground">
                        {{ t('trips.arrival') }}
                    </p>
                    <p
                        class="mt-1 font-semibold text-balance wrap-break-word"
                        :class="compact ? 'text-lg' : 'text-2xl sm:text-3xl'"
                    >
                        {{ trip.destination }}
                    </p>
                    <time
                        v-if="trip.arrives_at"
                        data-test="trip-arrival-time"
                        :datetime="trip.arrives_at"
                        :aria-label="t('trips.fields.arrives_at')"
                        class="mt-1 block text-sm text-muted-foreground"
                        >{{ formatSchedule(trip.arrives_at) }}</time
                    >
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        {{ t('trips.arrival_unknown') }}
                    </p>
                </div>
            </div>
            <div
                class="flex flex-wrap gap-x-5 gap-y-2 text-sm"
                :class="compact ? 'pt-1' : 'border-y border-border py-3'"
            >
                <p
                    v-if="trip.estimated_duration_minutes != null"
                    class="flex items-center gap-2"
                >
                    <Clock3
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    {{
                        t('trips.duration', {
                            duration: formatDuration(
                                trip.estimated_duration_minutes,
                            ),
                        })
                    }}
                </p>
                <p
                    v-if="!trip.cancelled_at"
                    class="flex items-center gap-2 font-medium"
                >
                    <Users
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />{{
                        trip.remaining_places === 0
                            ? t('trips.full')
                            : t('trips.places', {
                                  count: trip.remaining_places ?? 0,
                              })
                    }}
                </p>
            </div>
            <TripMemberLink
                v-if="trip.organizer"
                :trip-id="trip.id"
                :member="trip.organizer"
                data-test="trip-organizer"
            />
            <div
                v-if="trip.meeting_point && !compact"
                class="space-y-2 rounded-xl bg-muted/60 p-4"
            >
                <h2 class="flex items-start gap-2 text-sm font-medium">
                    <LockKeyhole
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />{{ t('trips.fields.meeting_point') }}
                </h2>
                <p class="whitespace-pre-wrap">{{ trip.meeting_point }}</p>
            </div>
        </template>
        <p v-if="trip.cancelled_at" class="font-semibold text-destructive">
            {{ t('trips.cancelled') }}
        </p>
        <p v-if="trip.cancellation_reason" class="whitespace-pre-wrap">
            {{ trip.cancellation_reason }}
        </p>
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

<style scoped>
.route-marker {
    border-radius: 50%;
}

@media (prefers-reduced-motion: no-preference) {
    .route-animated path {
        animation: route-arrival 1.2s linear infinite;
    }
    @keyframes route-arrival {
        from {
            stroke-dashoffset: 8;
        }
        to {
            stroke-dashoffset: 0;
        }
    }
}
</style>
