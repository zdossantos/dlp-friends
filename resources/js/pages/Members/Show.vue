<script setup lang="ts">
import PublicMemberProfile from '@/components/members/PublicMemberProfile.vue';
import type { PublicMember } from '@/types';

const props = defineProps<{
    member: PublicMember;
    backHref: string;
    likeHref?: string;
    likeLabel?: string;
    receivedReportsHref?: string;
    canBlock: boolean;
    canLike: boolean;
    canUnblock: boolean;
}>();
</script>

<template>
    <PublicMemberProfile v-bind="props" />
</template>
