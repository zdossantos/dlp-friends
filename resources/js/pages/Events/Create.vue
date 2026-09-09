<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EventForm from '@/components/events/EventForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/events';
const { t } = useTranslations();
</script>
<template>
    <Head :title="t('events.create.title')" />
    <main class="mx-auto w-full max-w-2xl space-y-6 px-4 py-8 pb-24 sm:px-6">
        <h1 class="text-3xl font-semibold">{{ t('events.create.title') }}</h1>
        <EventForm
            :action="store().url"
            method="post"
            :submit-label="t('events.create.submit')"
        />
    </main>
</template>
