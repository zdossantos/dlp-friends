<script setup lang="ts">
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
defineProps<{
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    busy?: boolean;
}>();
const emit = defineEmits<{ confirm: [] }>();
</script>
<template>
    <ResponsiveConfirmation
        :title="title"
        :description="description"
        :confirm-label="confirmLabel"
        :destructive="destructive"
        :busy="busy"
        test-prefix="event-confirm"
        @confirm="emit('confirm')"
        ><slot
    /></ResponsiveConfirmation>
</template>
