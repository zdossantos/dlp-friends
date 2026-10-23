<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TripInformation from '@/components/trips/TripInformation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDetail } from '@/types/trip';
defineProps<{ trip: TripDetail }>();
const { t } = useTranslations();
</script>
<template>
    <article
        class="flex min-w-0 flex-col gap-4 rounded-2xl border border-border bg-card p-5"
    >
        <TripInformation :trip="trip" compact /><Button
            as-child
            variant="outline"
            class="mt-auto w-full"
            ><Link :href="`/trips/${trip.id}`">{{
                t('trips.details')
            }}</Link></Button
        >
    </article>
</template>
