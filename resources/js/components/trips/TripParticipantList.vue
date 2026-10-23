<script setup lang="ts">
import TripMemberLink from '@/components/trips/TripMemberLink.vue';
import TripRegistrationActions from '@/components/trips/TripRegistrationActions.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDetail, TripMember } from '@/types/trip';
defineProps<{
    trip: TripDetail;
    participants: { id: number | null; member: TripMember }[];
}>();
const { t } = useTranslations();
</script>
<template>
    <ul class="divide-y divide-border">
        <li
            v-for="person in participants"
            :key="person.member.id"
            class="space-y-3 py-4"
        >
            <TripMemberLink :trip-id="trip.id" :member="person.member" />
            <TripRegistrationActions
                v-if="person.id && trip.can_manage"
                :trip="trip"
                :registration="{
                    id: person.id,
                    status: 'accepted',
                    reason: null,
                }"
            />
        </li>
    </ul>
    <p v-if="!participants.length">{{ t('trips.empty') }}</p>
</template>
