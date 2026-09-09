<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EventWorkspace from '@/components/events/EventWorkspace.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { EventWorkspaceProps } from '@/types/event';
defineProps<EventWorkspaceProps>();
const { t } = useTranslations();
</script>
<template>
    <Head :title="t('events.mine.title')" />
    <EventWorkspace v-bind="$props" />
</template>
