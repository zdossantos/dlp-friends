<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import type { TripMember } from '@/types/trip';
defineProps<{ tripId: number; member: TripMember }>();
</script>
<template>
    <Link
        :href="`/trips/${tripId}/members/${member.id}`"
        class="flex min-h-11 min-w-0 items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-ring"
        preserve-scroll
    >
        <UserAvatar
            :avatar="member.avatar ?? null"
            :display-name="member.display_name"
            class="size-10 shrink-0"
        />
        <span class="min-w-0 text-sm font-medium wrap-break-word">{{
            member.display_name
        }}</span>
    </Link>
</template>
