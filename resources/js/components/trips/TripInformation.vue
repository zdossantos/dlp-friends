<script setup lang="ts">
import {
    Car,
    TrainFront,
    BusFront,
    CalendarDays,
    Users,
    LockKeyhole,
} from '@lucide/vue';
import TripMemberLink from '@/components/trips/TripMemberLink.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDetail } from '@/types/trip';
defineProps<{ trip: TripDetail; compact?: boolean }>();
const { t, locale } = useTranslations();
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
                </div>
            </div>
            <div
                class="flex flex-wrap gap-x-5 gap-y-3 border-y border-border py-3 text-sm"
            >
                <p
                    v-if="trip.departs_at"
                    class="flex items-start gap-2"
                    :aria-label="t('trips.schedule')"
                >
                    <CalendarDays
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <time :datetime="trip.departs_at">{{
                        new Intl.DateTimeFormat(locale, {
                            dateStyle: compact ? 'medium' : 'long',
                            timeStyle: 'short',
                            timeZone: 'Europe/Paris',
                        }).format(new Date(trip.departs_at))
                    }}</time>
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
        animation: route-arrival 900ms ease-out both;
    }
    @keyframes route-arrival {
        from {
            stroke-dashoffset: 24;
            opacity: 0;
        }
        to {
            stroke-dashoffset: 0;
            opacity: 1;
        }
    }
}
</style>
