<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/conversation-reports';
import type { ConversationReport, ModerationPage } from '@/types/moderation';
defineProps<{ reports: ModerationPage<ConversationReport>; closed: boolean }>();
const { t, formatDate } = useTranslations();
</script>
<template>
    <Head :title="t('moderation.reports_title')" />
    <main class="grid gap-5 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('moderation.reports_title')"
            :description="t('moderation.review_description')"
        />
        <nav class="flex gap-3">
            <Link
                :href="index().url"
                :aria-current="!closed ? 'page' : undefined"
                >{{ t('moderation.open') }}</Link
            ><Link
                :href="index({ query: { closed: true } }).url"
                :aria-current="closed ? 'page' : undefined"
                >{{ t('moderation.closed') }}</Link
            >
        </nav>
        <p v-if="reports.data.length === 0">{{ t('moderation.empty') }}</p>
        <Link
            v-for="report in reports.data"
            :key="report.id"
            :href="show(report.id).url"
            class="grid gap-2 rounded-xl border bg-card p-4"
            ><span class="font-semibold"
                >{{ report.reporter.name }} → {{ report.target.name }}</span
            ><span>{{ t(`moderation.reasons.${report.reason}`) }}</span
            ><time>{{
                formatDate(report.created_at, { dateStyle: 'medium' })
            }}</time></Link
        >
        <nav class="flex gap-3">
            <Button v-if="reports.prev_page_url" as-child variant="outline"
                ><Link :href="reports.prev_page_url">{{
                    t('moderation.previous')
                }}</Link></Button
            ><Button v-if="reports.next_page_url" as-child variant="outline"
                ><Link :href="reports.next_page_url">{{
                    t('moderation.next')
                }}</Link></Button
            >
        </nav>
    </main>
</template>
