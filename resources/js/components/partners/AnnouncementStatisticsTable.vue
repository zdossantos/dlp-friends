<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { RotateCcw, Send } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import { dispatch, retry } from '@/routes/admin/partner-announcements';
import type {
    PartnerAnnouncementStatistics,
    PartnerAnnouncementStatus,
} from '@/types';

withDefaults(
    defineProps<{
        announcements: PartnerAnnouncementStatistics[];
        caption: string;
        emptyMessage: string;
        showOperations?: boolean;
    }>(),
    {
        showOperations: false,
    },
);

const { formatDate, formatNumber, t } = useTranslations();
const isDesktop = useMediaQuery('(min-width: 1024px)');

const statusKeys: Record<PartnerAnnouncementStatus, TranslationKey> = {
    draft: 'partners.announcements.status.draft',
    pending_approval: 'partners.announcements.status.pending_approval',
    approved: 'partners.announcements.status.approved',
    sending: 'partners.announcements.status.sending',
    sent: 'partners.announcements.status.sent',
    rejected: 'partners.announcements.status.rejected',
    cancelled: 'partners.announcements.status.cancelled',
};

function formatRate(rate: number): string {
    return formatNumber(rate / 100, {
        style: 'percent',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });
}

function canRetry(status: PartnerAnnouncementStatus): boolean {
    return status === 'sending' || status === 'sent';
}
</script>

<template>
    <p
        v-if="announcements.length === 0"
        class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
    >
        {{ emptyMessage }}
    </p>

    <div
        v-else-if="!isDesktop"
        data-test="partner-statistics-table"
        data-layout="cards"
        class="grid min-w-0 gap-3"
        :aria-label="caption"
    >
        <article
            v-for="announcement in announcements"
            :key="announcement.id"
            data-test="partner-statistics-card"
            class="min-w-0 rounded-xl border bg-card p-4 text-sm"
        >
            <p v-if="showOperations" class="break-words text-muted-foreground">
                {{
                    announcement.partner_name ??
                    t('administration.partner_statistics.unknown_partner')
                }}
            </p>
            <h3 class="text-base font-semibold break-words">
                {{ announcement.title }}
            </h3>
            <Badge variant="secondary" class="mt-2">{{
                t(statusKeys[announcement.status])
            }}</Badge>
            <dl class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.prepared') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.prepared) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.delivered') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.delivered) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.read') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.read) }} ·
                        {{ formatRate(announcement.read_rate) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.dismissed') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.dismissed) }} ·
                        {{ formatRate(announcement.dismiss_rate) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.unique_clicks') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.unique_clicks) }} ·
                        {{ formatRate(announcement.unique_click_rate) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('partners.statistics.total_clicks') }}
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatNumber(announcement.total_clicks) }}
                    </dd>
                </div>
                <template v-if="showOperations">
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('administration.partner_statistics.pending') }}
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatNumber(announcement.pending ?? 0) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('administration.partner_statistics.failed') }}
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatNumber(announcement.failed ?? 0) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ t('administration.partner_statistics.skipped') }}
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatNumber(announcement.skipped ?? 0) }}
                        </dd>
                    </div>
                </template>
            </dl>
            <div v-if="showOperations" class="mt-4">
                <Form
                    v-if="announcement.status === 'approved'"
                    v-bind="dispatch.form(announcement.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        class="min-h-11 w-full"
                        :data-test="`dispatch-partner-announcement-${announcement.id}`"
                        :disabled="
                            processing || Boolean(announcement.next_dispatch_at)
                        "
                    >
                        <Spinner v-if="processing" /><Send
                            v-else
                            aria-hidden="true"
                        />{{ t('administration.partner_statistics.dispatch') }}
                    </Button>
                </Form>
                <Form
                    v-else-if="canRetry(announcement.status)"
                    v-bind="retry.form(announcement.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        class="min-h-11 w-full"
                        :data-test="`retry-partner-announcement-${announcement.id}`"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" /><RotateCcw
                            v-else
                            aria-hidden="true"
                        />{{ t('administration.partner_statistics.retry') }}
                    </Button>
                </Form>
            </div>
        </article>
    </div>

    <div
        v-else
        data-test="partner-statistics-table"
        data-layout="table"
        role="region"
        :aria-label="caption"
        tabindex="0"
        class="max-w-full overflow-x-auto rounded-xl border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
    >
        <table
            class="w-full text-left text-sm"
            :class="showOperations ? 'min-w-[96rem]' : 'min-w-[74rem]'"
        >
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead class="border-b bg-muted/40 text-muted-foreground">
                <tr>
                    <th
                        v-if="showOperations"
                        scope="col"
                        class="px-3 py-3 font-medium"
                    >
                        {{ t('administration.partner_statistics.partner') }}
                    </th>
                    <th scope="col" class="px-3 py-3 font-medium">
                        {{ t('partners.statistics.announcement') }}
                    </th>
                    <th scope="col" class="px-3 py-3 font-medium">
                        {{ t('partners.statistics.status') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.prepared') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.delivered') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.read') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.dismissed') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.unique_clicks') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.total_clicks') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.read_rate') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.dismiss_rate') }}
                    </th>
                    <th scope="col" class="px-3 py-3 text-right font-medium">
                        {{ t('partners.statistics.unique_click_rate') }}
                    </th>
                    <template v-if="showOperations">
                        <th
                            scope="col"
                            class="px-3 py-3 text-right font-medium"
                        >
                            {{ t('administration.partner_statistics.pending') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3 text-right font-medium"
                        >
                            {{ t('administration.partner_statistics.failed') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3 text-right font-medium"
                        >
                            {{ t('administration.partner_statistics.skipped') }}
                        </th>
                        <th scope="col" class="px-3 py-3 font-medium">
                            {{ t('administration.partner_statistics.actions') }}
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="announcement in announcements"
                    :key="announcement.id"
                    class="border-b last:border-0"
                >
                    <td
                        v-if="showOperations"
                        class="max-w-56 px-3 py-4 font-medium"
                    >
                        {{
                            announcement.partner_name ??
                            t(
                                'administration.partner_statistics.unknown_partner',
                            )
                        }}
                    </td>
                    <th scope="row" class="max-w-64 px-3 py-4 font-medium">
                        {{ announcement.title }}
                    </th>
                    <td class="px-3 py-4">
                        <Badge variant="secondary">
                            {{ t(statusKeys[announcement.status]) }}
                        </Badge>
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.prepared) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.delivered) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.read) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.dismissed) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.unique_clicks) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatNumber(announcement.total_clicks) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatRate(announcement.read_rate) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatRate(announcement.dismiss_rate) }}
                    </td>
                    <td class="px-3 py-4 text-right tabular-nums">
                        {{ formatRate(announcement.unique_click_rate) }}
                    </td>
                    <template v-if="showOperations">
                        <td class="px-3 py-4 text-right tabular-nums">
                            {{ formatNumber(announcement.pending ?? 0) }}
                        </td>
                        <td class="px-3 py-4 text-right tabular-nums">
                            {{ formatNumber(announcement.failed ?? 0) }}
                        </td>
                        <td class="px-3 py-4 text-right tabular-nums">
                            {{ formatNumber(announcement.skipped ?? 0) }}
                        </td>
                        <td class="px-3 py-4">
                            <Form
                                v-if="announcement.status === 'approved'"
                                v-bind="dispatch.form(announcement.id)"
                                :options="{ preserveScroll: true }"
                                v-slot="{ errors, processing }"
                            >
                                <Button
                                    type="submit"
                                    class="min-h-11"
                                    :data-test="`dispatch-partner-announcement-${announcement.id}`"
                                    :disabled="
                                        processing ||
                                        Boolean(announcement.next_dispatch_at)
                                    "
                                    :aria-busy="processing ? 'true' : undefined"
                                    :aria-describedby="
                                        errors.announcement ||
                                        errors.destination_url
                                            ? `dispatch-error-${announcement.id}`
                                            : undefined
                                    "
                                >
                                    <Spinner v-if="processing" />
                                    <Send v-else aria-hidden="true" />
                                    {{
                                        processing
                                            ? t(
                                                  'administration.partner_statistics.dispatching',
                                              )
                                            : t(
                                                  'administration.partner_statistics.dispatch',
                                              )
                                    }}
                                </Button>
                                <InputError
                                    :id="`dispatch-error-${announcement.id}`"
                                    :message="
                                        errors.announcement ||
                                        errors.destination_url
                                    "
                                    class="mt-2"
                                />
                                <p
                                    v-if="announcement.next_dispatch_at"
                                    class="mt-2 max-w-72 text-sm text-destructive"
                                    role="alert"
                                >
                                    {{
                                        t(
                                            'administration.partner_statistics.dispatch_available_at',
                                            {
                                                date: formatDate(
                                                    announcement.next_dispatch_at,
                                                    {
                                                        dateStyle: 'full',
                                                        timeStyle: 'short',
                                                        timeZone:
                                                            'Europe/Paris',
                                                    },
                                                ),
                                            },
                                        )
                                    }}
                                </p>
                            </Form>
                            <Form
                                v-else-if="canRetry(announcement.status)"
                                v-bind="retry.form(announcement.id)"
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    class="min-h-11"
                                    :data-test="`retry-partner-announcement-${announcement.id}`"
                                    :disabled="processing"
                                    :aria-busy="processing ? 'true' : undefined"
                                >
                                    <Spinner v-if="processing" />
                                    <RotateCcw v-else aria-hidden="true" />
                                    {{
                                        processing
                                            ? t(
                                                  'administration.partner_statistics.retrying',
                                              )
                                            : t(
                                                  'administration.partner_statistics.retry',
                                              )
                                    }}
                                </Button>
                            </Form>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
