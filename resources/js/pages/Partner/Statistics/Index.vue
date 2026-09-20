<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChartBar } from '@lucide/vue';
import AnnouncementStatisticsTable from '@/components/partners/AnnouncementStatisticsTable.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PartnerAnnouncementStatistics } from '@/types';

defineProps<{
    announcements: PartnerAnnouncementStatistics[];
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('partners.statistics.page_title')" />

    <main
        class="mx-auto w-full max-w-[96rem] min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:py-10"
    >
        <header class="space-y-2">
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-primary/10 p-2 text-primary">
                    <ChartBar class="size-6" aria-hidden="true" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ t('partners.statistics.title') }}
                </h1>
            </div>
            <p class="max-w-3xl text-muted-foreground">
                {{ t('partners.statistics.description') }}
            </p>
        </header>

        <AnnouncementStatisticsTable
            :announcements="announcements"
            :caption="t('partners.statistics.caption')"
            :empty-message="t('partners.statistics.empty')"
        />
    </main>
</template>
