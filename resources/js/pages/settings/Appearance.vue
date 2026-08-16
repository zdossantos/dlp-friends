<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apparence',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apparence" />

    <h1 class="sr-only">Apparence</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apparence"
            description="Choisissez le thème le plus confortable pour vous."
        />
        <AppearanceTabs />
    </div>
</template>
