<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TripListWorkspace from '@/components/trips/TripListWorkspace.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TripPage } from '@/types/trip';
defineProps<{ trips: TripPage; filters?: { transport?: string } }>();
const { t } = useTranslations();
</script>
<template>
    <Head :title="t('trips.title')" /><TripListWorkspace
        :trips="trips"
        :filters="filters"
    />
</template>
