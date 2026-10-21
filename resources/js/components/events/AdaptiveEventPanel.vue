<script setup lang="ts">
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import { restoreEventWorkspaceScroll } from '@/lib/eventWorkspaceScroll';
const props = defineProps<{
    open: boolean;
    preserveState?: boolean;
    only?: string[];
    title?: string;
    description?: string;
    a11yHeading?: string | null;
    a11ySummary?: string | null;
    backHref?: string | null;
    backLabel?: string;
    backDataTest?: string;
    fullBleed?: boolean;
    fillHeight?: boolean;
    backInHeader?: boolean;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
</script>
<template>
    <ResponsivePanel
        v-bind="props"
        test-id="event-panel"
        @update:open="emit('update:open', $event)"
        @restore-scroll="restoreEventWorkspaceScroll"
        ><slot
    /></ResponsivePanel>
</template>
