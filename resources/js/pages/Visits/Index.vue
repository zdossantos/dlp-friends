<script setup lang="ts">
import VisitWorkspace from '@/components/visits/VisitWorkspace.vue';
import type { VisitWorkspaceProps } from '@/types/visit';
defineProps<VisitWorkspaceProps>();
</script>
<template><VisitWorkspace v-bind="$props" /></template>
