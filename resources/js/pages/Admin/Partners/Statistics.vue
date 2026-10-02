<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AnnouncementStatisticsTable from '@/components/partners/AnnouncementStatisticsTable.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { PartnerAnnouncementStatistics } from '@/types';

defineProps<{
    announcements: PartnerAnnouncementStatistics[];
    eligibleRecipientCount: number;
}>();

const { formatNumber, t } = useTranslations();
</script>

<template>
    <Head :title="t('administration.partner_statistics.page_title')" />

    <main class="flex min-w-0 flex-1 flex-col gap-8 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('administration.partner_statistics.title')"
            :description="t('administration.partner_statistics.description')"
        />

        <p
            data-test="eligible-partner-announcement-recipients"
            class="rounded-2xl border bg-card p-4 text-sm text-muted-foreground"
        >
            {{
                t('administration.partner_statistics.eligible_recipients', {
                    count: formatNumber(eligibleRecipientCount),
                })
            }}
        </p>

        <AnnouncementStatisticsTable
            :announcements="announcements"
            :caption="t('administration.partner_statistics.caption')"
            :empty-message="t('administration.partner_statistics.empty')"
            show-operations
        />
    </main>
</template>
