<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/trip-departure-reports';
import type { ModerationPage, TripDepartureReport } from '@/types/moderation';
defineProps<{ reports: ModerationPage<TripDepartureReport> }>();
const { t, formatDate } = useTranslations();
</script>
<template>
    <section class="grid min-w-0 gap-4" aria-labelledby="trip-reports-title">
        <h2 id="trip-reports-title" class="text-xl font-semibold">
            <Link :href="index().url">{{ t('moderation.trip_reports') }}</Link>
        </h2>
        <p v-if="!reports.data.length" class="text-muted-foreground">
            {{ t('moderation.empty') }}
        </p>
        <article
            v-for="report in reports.data"
            :key="report.id"
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-4"
        >
            <div class="min-w-0 space-y-2">
                <p class="font-medium break-words">{{ report.target.name }}</p>
                <p class="text-sm text-muted-foreground">
                    {{
                        formatDate(report.created_at, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })
                    }}
                </p>
                <Badge :variant="report.closed_at ? 'secondary' : 'default'">{{
                    t(
                        report.closed_at
                            ? 'moderation.state_closed'
                            : 'moderation.state_open',
                    )
                }}</Badge>
            </div>
            <Button as-child variant="outline"
                ><Link :href="show(report.id).url">{{
                    t('moderation.review_action')
                }}</Link></Button
            >
        </article>
        <nav
            v-if="reports.last_page > 1"
            :aria-label="t('moderation.pagination')"
            class="flex flex-wrap items-center gap-3"
        >
            <Button v-if="reports.prev_page_url" as-child variant="outline"
                ><Link :href="reports.prev_page_url" preserve-scroll>{{
                    t('moderation.previous')
                }}</Link></Button
            >
            <span>{{
                t('moderation.page', {
                    current: reports.current_page,
                    last: reports.last_page,
                })
            }}</span>
            <Button v-if="reports.next_page_url" as-child variant="outline"
                ><Link :href="reports.next_page_url" preserve-scroll>{{
                    t('moderation.next')
                }}</Link></Button
            >
        </nav>
    </section>
</template>
