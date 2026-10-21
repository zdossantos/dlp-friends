<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import UserAvatar from '@/components/profile/UserAvatar.vue';
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
            <Link
                :href="`/trips/${trip.id}/members/${person.member.id}`"
                class="flex min-h-11 items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-ring"
                preserve-scroll
                ><UserAvatar
                    :avatar="person.member.avatar ?? null"
                    :display-name="person.member.display_name"
                    class="size-11 shrink-0"
                /><span class="min-w-0 wrap-break-word">{{
                    person.member.display_name
                }}</span></Link
            ><TripRegistrationActions
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
