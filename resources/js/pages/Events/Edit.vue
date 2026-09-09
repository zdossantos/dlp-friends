<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EventForm from '@/components/events/EventForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/events';
import type { EventDetail } from '@/types/event';
const props = defineProps<{ event: EventDetail }>();
const { t } = useTranslations();
</script>
<template>
    <Head :title="t('events.edit.title')" />
    <main class="mx-auto w-full max-w-2xl space-y-6 px-4 py-8 pb-24 sm:px-6">
        <h1 class="text-3xl font-semibold">{{ t('events.edit.title') }}</h1>
        <EventForm
            :event="event"
            :action="update(props.event.id).url"
            method="patch"
            :submit-label="t('events.edit.submit')"
        />
    </main>
</template>
