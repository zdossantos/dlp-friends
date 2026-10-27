<script setup lang="ts">
import PublicProfile from '@/components/members/PublicMemberProfile.vue';
import type { PublicMember } from '@/types';

defineProps<{
    member: PublicMember;
    backHref: string;
    receivedReportsHref?: string;
}>();
</script>

<template>
    <PublicProfile
        :member="member"
        :back-href="backHref"
        :received-reports-href="receivedReportsHref"
        :can-block="false"
        :can-like="false"
        :can-unblock="false"
    />
</template>
