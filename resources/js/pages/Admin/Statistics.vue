<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { parseDate } from '@internationalized/date';
import { CalendarDays } from '@lucide/vue';
import type { DateRange } from 'reka-ui';
import { computed, shallowRef, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import UsageChart from '@/components/admin/UsageChart.vue';
import UsageDonut from '@/components/admin/UsageDonut.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import type { TranslationKey } from '@/composables/useTranslations';
import { useTranslations } from '@/composables/useTranslations';
import { index as statistics } from '@/routes/admin/statistics';
import type { UsageMetric, UsageStatistics } from '@/types/usage-statistics';
import { usageMetricColors } from '@/types/usage-statistics';

const { t, locale, formatDate: localizeDate, formatNumber } = useTranslations();

const props = defineProps<{
    usage: UsageStatistics;
}>();

const form = useForm({
    from: props.usage.period.from,
    to: props.usage.period.to,
});
watch(
    () => props.usage.period,
    (period) => {
        form.from = period.from;
        form.to = period.to;
    },
);
const today = new Date().toISOString().slice(0, 10);
const apply = () =>
    form.get(statistics().url, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
function preset(days: number) {
    const from = new Date(`${today}T00:00:00Z`);
    from.setUTCDate(from.getUTCDate() - days + 1);
    form.from = from.toISOString().slice(0, 10);
    form.to = today;
    apply();
}
const day = (value: string) =>
    localizeDate(`${value}T00:00:00Z`, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    });
const percent = (value: number, total: number) =>
    formatNumber(total ? value / total : 0, {
        style: 'percent',
        maximumFractionDigits: 1,
    });
function change(metric: UsageMetric): string {
    const { current, previous } = props.usage.metrics[metric];

    if (previous === 0) {
        return t(current === 0 ? 'usage.unchanged' : 'usage.new_activity');
    }

    return formatNumber((current - previous) / previous, {
        style: 'percent',
        signDisplay: 'exceptZero',
        maximumFractionDigits: 1,
    });
}
const highlights: UsageMetric[] = [
    'registrations',
    'contributors',
    'matches',
    'privateMessages',
];
const charts: {
    id: string;
    title: TranslationKey;
    description: TranslationKey;
    series: UsageMetric[];
    kind?: 'bar' | 'line';
}[] = [
    {
        id: 'growth',
        kind: 'bar',
        title: 'usage.growth',
        description: 'usage.growth_description',
        series: ['registrations'],
    },
    {
        id: 'engagement',
        title: 'usage.engagement',
        description: 'usage.engagement_description',
        series: ['contributors'],
    },
    {
        id: 'discovery',
        title: 'usage.discovery',
        description: 'usage.discovery_description',
        series: ['likes', 'passes', 'matches'],
    },
    {
        id: 'messaging',
        title: 'usage.messaging',
        description: 'usage.messaging_description',
        series: ['privateMessages', 'groupMessages'],
    },
    {
        id: 'activities',
        kind: 'bar',
        title: 'usage.activities',
        description: 'usage.activities_description',
        series: ['events', 'visits', 'trips'],
    },
];
const range = computed(() => ({
    start: parseDate(props.usage.period.from),
    end: parseDate(props.usage.period.to),
}));
const calendarOpen = shallowRef(false);
function selectRange(value: DateRange) {
    if (value.start && value.end) {
        calendarOpen.value = false;
        form.from = value.start.toString();
        form.to = value.end.toString();
        apply();
    }
}
</script>
<template>
    <Head :title="t('administration.navigation.statistics')" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('administration.navigation.statistics')"
            :description="t('usage.description')"
        />

        <section :aria-label="t('usage.title')" class="space-y-4">
            <form
                class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
                @submit.prevent="apply"
            >
                <Popover v-model:open="calendarOpen">
                    <PopoverTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            class="justify-start text-left font-normal"
                            :disabled="form.processing"
                            :aria-label="t('usage.choose_range')"
                            data-test="usage-range-trigger"
                        >
                            <CalendarDays class="mr-2 size-4" />
                            {{ day(usage.period.from) }} –
                            {{ day(usage.period.to) }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        class="w-auto p-0"
                        align="start"
                        data-test="usage-calendar"
                    >
                        <RangeCalendar
                            :model-value="range"
                            :locale="locale"
                            :min-value="parseDate('2000-01-01')"
                            :max-value="parseDate(today)"
                            :week-starts-on="1"
                            initial-focus
                            @update:model-value="selectRange"
                        />
                    </PopoverContent>
                </Popover>
                <InputError :message="form.errors.from" />
                <InputError :message="form.errors.to" />
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="days in [7, 30, 90]"
                        :key="days"
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        :data-test="`usage-preset-${days}`"
                        @click="preset(days)"
                        >{{ t('usage.preset', { days }) }}</Button
                    >
                </div>
            </form>
            <div
                class="space-y-1 text-sm text-muted-foreground"
                aria-live="polite"
            >
                <p class="font-medium text-foreground">
                    {{
                        t('usage.period', {
                            from: day(usage.period.from),
                            to: day(usage.period.to),
                            days: usage.period.days,
                        })
                    }}
                </p>
                <p>
                    {{
                        t('usage.comparison', {
                            from: day(usage.period.previousFrom),
                            to: day(usage.period.previousTo),
                        })
                    }}
                </p>
                <p>{{ t('usage.calendar') }}</p>
                <p>{{ t('usage.scope') }}</p>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="key in highlights" :key="key"
                ><CardHeader
                    ><CardDescription>{{
                        t(`usage.metrics.${key}`)
                    }}</CardDescription
                    ><CardTitle class="text-3xl tabular-nums">{{
                        formatNumber(usage.metrics[key].current)
                    }}</CardTitle></CardHeader
                ><CardContent class="space-y-1 text-sm"
                    ><p>{{ t('usage.change') }}: {{ change(key) }}</p>
                    <p class="text-muted-foreground">
                        {{ t('usage.previous') }}:
                        {{ formatNumber(usage.metrics[key].previous) }}
                    </p></CardContent
                ></Card
            >
        </section>

        <UsageChart
            id="overview"
            :title="'usage.overview'"
            description="usage.overview_description"
            :daily="usage.daily"
            :series="Object.keys(usage.metrics) as UsageMetric[]"
        />
        <section class="grid min-w-0 gap-6 xl:grid-cols-2">
            <UsageDonut
                id="discovery"
                :colors="[usageMetricColors.likes, usageMetricColors.passes]"
                :title="'usage.decisions_title'"
                description="usage.decisions_description"
                :values="[
                    {
                        label: 'usage.metrics.likes',
                        value: usage.metrics.likes.current,
                    },
                    {
                        label: 'usage.metrics.passes',
                        value: usage.metrics.passes.current,
                    },
                ]"
            />
            <UsageChart
                v-for="chart in charts"
                :key="chart.id"
                v-bind="chart"
                :daily="usage.daily"
            />
            <Card class="min-w-0"
                ><CardHeader
                    ><CardTitle>{{ t('usage.activation_title') }}</CardTitle
                    ><CardDescription>{{
                        t('usage.activation_description')
                    }}</CardDescription></CardHeader
                ><CardContent class="space-y-6"
                    ><div
                        v-for="(value, key) in usage.activation"
                        :key="key"
                        class="space-y-2"
                    >
                        <div
                            class="flex flex-wrap justify-between gap-2 text-sm"
                        >
                            <span>{{ t(`usage.${key}`) }}</span
                            ><span class="font-semibold tabular-nums"
                                >{{ formatNumber(value) }} ·
                                {{
                                    percent(value, usage.activation.registered)
                                }}</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                class="h-full bg-primary"
                                :style="{
                                    width: `${usage.activation.registered ? (value / usage.activation.registered) * 100 : 0}%`,
                                }"
                            />
                        </div></div></CardContent
            ></Card>
            <UsageDonut
                id="conversations"
                :colors="['#2563eb', '#d97706']"
                :title="'usage.conversation_title'"
                description="usage.conversation_description"
                :values="[
                    {
                        label: 'usage.with_messages',
                        value: usage.conversations.withMessages,
                    },
                    {
                        label: 'usage.without_messages',
                        value:
                            usage.conversations.total -
                            usage.conversations.withMessages,
                    },
                ]"
            />
            <Card
                ><CardHeader
                    ><CardTitle>{{ t('usage.community_title') }}</CardTitle
                    ><CardDescription>{{
                        t('usage.snapshot')
                    }}</CardDescription></CardHeader
                ><CardContent
                    ><dl class="space-y-3 text-sm">
                        <div
                            v-for="(value, key) in usage.community"
                            :key="key"
                            class="flex justify-between gap-3"
                        >
                            <dt>{{ t(`usage.${key}`) }}</dt>
                            <dd class="font-semibold tabular-nums">
                                {{ formatNumber(value) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-sm text-muted-foreground">
                        {{ t('usage.status_note') }}
                    </p></CardContent
                ></Card
            >
            <Card class="xl:col-span-2"
                ><CardHeader
                    ><CardTitle>{{ t('usage.interests_title') }}</CardTitle
                    ><CardDescription>{{
                        t('usage.interests_description')
                    }}</CardDescription></CardHeader
                ><CardContent
                    ><ol
                        v-if="usage.interests.length"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <li
                            v-for="interest in usage.interests"
                            :key="interest.name"
                            class="space-y-2"
                        >
                            <div class="flex justify-between gap-3 text-sm">
                                <span>{{ interest.name }}</span
                                ><span class="font-semibold tabular-nums">{{
                                    formatNumber(interest.count)
                                }}</span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full bg-primary"
                                    :style="{
                                        width: `${(interest.count / usage.interests[0].count) * 100}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ t('usage.interests_empty') }}
                    </p></CardContent
                ></Card
            >
        </section>

        <Card class="min-w-0"
            ><CardHeader
                ><CardTitle id="usage-totals">{{
                    t('usage.totals_title')
                }}</CardTitle
                ><CardDescription>{{
                    t('usage.totals_description')
                }}</CardDescription></CardHeader
            ><CardContent
                ><div
                    class="overflow-x-auto"
                    tabindex="0"
                    role="region"
                    aria-labelledby="usage-totals"
                >
                    <table class="w-full text-left text-sm tabular-nums">
                        <thead>
                            <tr class="border-b">
                                <th scope="col" class="p-3">
                                    {{ t('usage.metric') }}
                                </th>
                                <th scope="col" class="p-3 text-right">
                                    {{ t('usage.current') }}
                                </th>
                                <th scope="col" class="p-3 text-right">
                                    {{ t('usage.previous') }}
                                </th>
                                <th scope="col" class="p-3 text-right">
                                    {{ t('usage.change') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(values, key) in usage.metrics"
                                :key="key"
                                class="border-b last:border-0"
                            >
                                <th scope="row" class="p-3 font-medium">
                                    {{ t(`usage.metrics.${key}`) }}
                                </th>
                                <td class="p-3 text-right">
                                    {{ formatNumber(values.current) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatNumber(values.previous) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ change(key) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <details class="mt-4 text-sm">
                    <summary
                        class="cursor-pointer rounded py-2 font-medium focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        {{ t('usage.method_title') }}
                    </summary>
                    <p class="mt-2 leading-relaxed text-muted-foreground">
                        {{ t('usage.method') }}
                    </p>
                </details></CardContent
            ></Card
        >
    </main>
</template>
