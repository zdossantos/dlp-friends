<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/conversation-reports';
import { index as members } from '@/routes/admin/members';
import { show as profile } from '@/routes/members';
import type { ConversationReport, ModerationPage } from '@/types/moderation';

const props = defineProps<{
    reports: ModerationPage<ConversationReport>;
    status: 'all' | 'open' | 'closed';
    counts: { open: number; closed: number };
    target: { id: number; name: string } | null;
    source: 'profile' | 'admin';
}>();
const { t, formatDate } = useTranslations();
const isDesktop = useMediaQuery('(min-width: 1024px)');
const baseQuery = computed(() =>
    props.target ? { target: props.target.id, source: props.source } : {},
);
const tabs = computed(() => [
    ...(props.target
        ? [
              {
                  value: 'all',
                  label: t('moderation.all'),
                  count: props.counts.open + props.counts.closed,
                  href: index({ query: baseQuery.value }).url,
              },
          ]
        : []),
    {
        value: 'open',
        label: t('moderation.pending'),
        count: props.counts.open,
        href: index({ query: { ...baseQuery.value, closed: false } }).url,
    },
    {
        value: 'closed',
        label: t('moderation.closed'),
        count: props.counts.closed,
        href: index({ query: { ...baseQuery.value, closed: true } }).url,
    },
]);
function reviewHref(report: ConversationReport): string {
    return show(report.id, {
        query: {
            ...baseQuery.value,
            ...(props.status !== 'all'
                ? { closed: props.status === 'closed' }
                : {}),
            list_page: props.reports.current_page,
        },
    }).url;
}
function date(value: string): string {
    return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>
<template>
    <Head
        :title="
            target
                ? t('moderation.received_reports')
                : t('moderation.reports_title')
        "
    />
    <main class="flex min-w-0 flex-col gap-6 p-4 sm:p-6">
        <AdminPageHeader
            :title="
                target
                    ? t('moderation.received_reports')
                    : t('moderation.reports_title')
            "
            :description="
                target
                    ? t('moderation.received_description', {
                          name: target.name,
                      })
                    : t('moderation.review_description')
            "
        />
        <div v-if="target" class="flex flex-wrap gap-2">
            <Button as-child variant="outline">
                <Link
                    data-test="reports-origin"
                    :href="
                        source === 'profile'
                            ? profile(target.id).url
                            : members({ query: { member: target.id } }).url
                    "
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    {{
                        t(
                            source === 'profile'
                                ? 'moderation.back_to_profile'
                                : 'moderation.back_to_member',
                        )
                    }}
                </Link>
            </Button>
            <Button as-child variant="ghost"
                ><Link :href="index().url">{{
                    t('moderation.global_queue')
                }}</Link></Button
            >
        </div>
        <nav class="flex flex-wrap gap-2" :aria-label="t('moderation.state')">
            <Button
                v-for="tab in tabs"
                :key="tab.value"
                as-child
                :variant="status === tab.value ? 'default' : 'outline'"
            >
                <Link
                    :href="tab.href"
                    :aria-current="status === tab.value ? 'page' : undefined"
                >
                    {{ tab.label }}
                    <span class="tabular-nums">{{ tab.count }}</span>
                </Link>
            </Button>
        </nav>
        <p
            v-if="reports.data.length === 0"
            role="status"
            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
        >
            {{
                t(
                    target
                        ? 'moderation.empty_received'
                        : status === 'closed'
                          ? 'moderation.empty_closed'
                          : 'moderation.empty_open',
                )
            }}
        </p>
        <div
            v-else-if="isDesktop"
            class="overflow-x-auto rounded-xl border bg-card"
        >
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    {{
                        t(
                            target
                                ? 'moderation.received_reports'
                                : 'moderation.reports_title',
                        )
                    }}
                </caption>
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th scope="col" class="p-4">
                            {{ t('moderation.reporter') }}
                        </th>
                        <th scope="col" class="p-4">
                            {{ t('moderation.target') }}
                        </th>
                        <th scope="col" class="p-4">
                            {{ t('moderation.reason') }}
                        </th>
                        <th scope="col" class="p-4">
                            {{ t('moderation.date') }}
                        </th>
                        <th scope="col" class="p-4">
                            {{ t('moderation.state') }}
                        </th>
                        <th scope="col" class="p-4">
                            <span class="sr-only">{{
                                t('moderation.review_action')
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="report in reports.data"
                        :key="report.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="max-w-48 p-4 break-words">
                            {{ report.reporter.name }}
                        </td>
                        <td class="max-w-48 p-4 font-medium break-words">
                            {{ report.target.name }}
                        </td>
                        <td class="p-4">
                            {{ t(`moderation.reasons.${report.reason}`) }}
                        </td>
                        <td class="p-4 tabular-nums">
                            <time :datetime="report.created_at">{{
                                date(report.created_at)
                            }}</time>
                        </td>
                        <td class="p-4">
                            <Badge
                                :variant="
                                    report.closed_at ? 'secondary' : 'default'
                                "
                                >{{
                                    t(
                                        report.closed_at
                                            ? 'moderation.state_closed'
                                            : 'moderation.state_open',
                                    )
                                }}</Badge
                            >
                        </td>
                        <td class="p-4">
                            <Button as-child variant="outline"
                                ><Link
                                    data-test="review-report"
                                    :href="reviewHref(report)"
                                    >{{ t('moderation.review_action')
                                    }}<ArrowUpRight
                                        class="size-4"
                                        aria-hidden="true" /></Link
                            ></Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="grid gap-3">
            <article
                v-for="report in reports.data"
                :key="report.id"
                class="grid min-w-0 gap-4 rounded-xl border bg-card p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Badge
                        :variant="report.closed_at ? 'secondary' : 'default'"
                        >{{
                            t(
                                report.closed_at
                                    ? 'moderation.state_closed'
                                    : 'moderation.state_open',
                            )
                        }}</Badge
                    >
                    <time
                        :datetime="report.created_at"
                        class="text-xs text-muted-foreground"
                        >{{ date(report.created_at) }}</time
                    >
                </div>
                <dl class="grid gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('moderation.reporter') }}
                        </dt>
                        <dd class="break-words">{{ report.reporter.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('moderation.target') }}
                        </dt>
                        <dd class="font-semibold break-words">
                            {{ report.target.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('moderation.reason') }}
                        </dt>
                        <dd>{{ t(`moderation.reasons.${report.reason}`) }}</dd>
                    </div>
                </dl>
                <Button as-child variant="outline"
                    ><Link data-test="review-report" :href="reviewHref(report)"
                        >{{ t('moderation.review_action')
                        }}<ArrowUpRight
                            class="size-4"
                            aria-hidden="true" /></Link
                ></Button>
            </article>
        </div>
        <nav
            v-if="reports.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
            :aria-label="t('moderation.pagination')"
        >
            <Button v-if="reports.prev_page_url" as-child variant="outline"
                ><Link :href="reports.prev_page_url">{{
                    t('moderation.previous')
                }}</Link></Button
            >
            <span class="text-sm text-muted-foreground">{{
                t('moderation.page', {
                    current: reports.current_page,
                    last: reports.last_page,
                })
            }}</span>
            <Button v-if="reports.next_page_url" as-child variant="outline"
                ><Link :href="reports.next_page_url">{{
                    t('moderation.next')
                }}</Link></Button
            >
        </nav>
    </main>
</template>
