<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import TripDepartureReportList from '@/components/admin/TripDepartureReportList.vue';
import BackLink from '@/components/common/BackLink.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index as history } from '@/routes/admin/conversation-reports';
import { index } from '@/routes/admin/trip-departure-reports';
import type { ModerationPage, TripDepartureReport } from '@/types/moderation';
const props = defineProps<{
    reports: ModerationPage<TripDepartureReport>;
    status: 'all' | 'open' | 'closed';
    counts: { open: number; closed: number };
    target: { id: number; name: string } | null;
}>();
const { t } = useTranslations();
</script>
<template>
    <Head :title="t('moderation.trip_reports')" />
    <main class="grid min-w-0 gap-6 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('moderation.trip_reports')"
            :description="t('moderation.trip_report_description')"
        />
        <Button as-child variant="outline" class="justify-self-start"
            ><BackLink
                :href="
                    history({ query: target ? { target: target.id } : {} }).url
                "
                >{{ t('moderation.back_to_list') }}</BackLink
            ></Button
        >
        <nav class="flex flex-wrap gap-3">
            <Button
                v-for="closed in [false, true]"
                :key="String(closed)"
                as-child
                :variant="
                    status === (closed ? 'closed' : 'open')
                        ? 'default'
                        : 'outline'
                "
                ><Link
                    :href="
                        index({
                            query: {
                                ...(props.target
                                    ? { target: props.target.id }
                                    : {}),
                                closed,
                            },
                        }).url
                    "
                    >{{
                        t(closed ? 'moderation.closed' : 'moderation.open')
                    }}
                    ({{ counts[closed ? 'closed' : 'open'] }})</Link
                ></Button
            >
        </nav>
        <TripDepartureReportList :reports="reports" />
    </main>
</template>
