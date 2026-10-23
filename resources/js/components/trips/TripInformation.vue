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
const { t, locale } = useTranslations();
function formatSchedule(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: props.compact ? 'medium' : 'long',
        timeStyle: 'short',
        timeZone: 'Europe/Paris',
    }).format(new Date(value));
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
    <section class="min-w-0 space-y-4 wrap-break-word">
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
                class="relative grid min-w-0 grid-cols-[1rem_1fr] gap-x-3 gap-y-5"
                :class="compact ? '' : 'py-2'"
            >
                <svg
                    class="route-line absolute top-3 bottom-3 left-0 h-[calc(100%-1.5rem)] w-4 overflow-visible text-primary"
                    viewBox="0 0 16 100"
                    preserveAspectRatio="none"
                    fill="none"
                    aria-hidden="true"
                    :class="{ 'route-animated': !compact }"
                >
                    <path
                        d="M8 6V94"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-dasharray="3 5"
                    />
                    <circle
                        cx="8"
                        cy="6"
                        r="4"
                        fill="var(--color-card)"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <circle cx="8" cy="94" r="4" fill="currentColor" />
                </svg>
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
                class="flex flex-wrap gap-x-5 gap-y-3 border-y border-border py-3 text-sm"
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
