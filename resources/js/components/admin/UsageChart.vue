<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import type { UsageDay, UsageMetric } from '@/types/usage-statistics';
import { usageMetricColors } from '@/types/usage-statistics';
import UsagePlot from './UsagePlot.vue';

const props = defineProps<{
    id: string;
    title: TranslationKey;
    description: TranslationKey;
    daily: UsageDay[];
    series: UsageMetric[];
    kind?: 'line' | 'bar';
}>();
const { t, formatDate, formatNumber } = useTranslations();
const hidden = ref<UsageMetric[]>([]);
const visible = computed(() =>
    props.series.filter((key) => !hidden.value.includes(key)),
);
const empty = computed(() =>
    props.daily.every((day) => props.series.every((key) => day[key] === 0)),
);
const color = (index: number) => usageMetricColors[props.series[index]];
const dash = (index: number) => ['', '8 4', '2 4'][index % 3];
const option = computed(() => ({
    tooltip: { trigger: 'axis', axisPointer: { type: 'line' } },
    grid: { left: 45, right: 12, top: 20, bottom: 35 },
    xAxis: {
        type: 'category',
        data: props.daily.map((day) => day.date),
        axisLabel: {
            formatter: (value: string) => date(value),
            hideOverlap: true,
        },
    },
    yAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { color: '#94a3b833' } },
    },
    series: props.series
        .filter((key) => visible.value.includes(key))
        .map((key) => ({
            name: t(`usage.metrics.${key}`),
            type: props.kind ?? 'line',
            data: props.daily.map((day) => day[key]),
            showSymbol: props.daily.length === 1,
            itemStyle: { color: color(props.series.indexOf(key)) },
            lineStyle: {
                width: 2,
                type: ['solid', 'dashed', 'dotted'][
                    props.series.indexOf(key) % 3
                ],
            },
            emphasis: { focus: 'series' },
        })),
}));
const date = (value: string) =>
    formatDate(`${value}T00:00:00Z`, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    });
function toggle(key: UsageMetric) {
    hidden.value = hidden.value.includes(key)
        ? hidden.value.filter((item) => item !== key)
        : [...hidden.value, key];
}
</script>

<template>
    <Card class="min-w-0" :data-test="`usage-chart-${id}`">
        <CardHeader>
            <CardTitle :id="`usage-title-${id}`">{{ t(title) }}</CardTitle>
            <CardDescription>{{ t(description) }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <UsagePlot :option="option" />
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(key, index) in series"
                    :key="key"
                    type="button"
                    :aria-pressed="!hidden.includes(key)"
                    :disabled="visible.length === 1 && visible.includes(key)"
                    class="flex min-h-10 items-center gap-2 rounded-md border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-default"
                    :class="
                        hidden.includes(key)
                            ? 'text-muted-foreground'
                            : 'bg-muted text-foreground'
                    "
                    @click="toggle(key)"
                >
                    <svg width="22" height="8" aria-hidden="true">
                        <line
                            x1="0"
                            x2="22"
                            y1="4"
                            y2="4"
                            :stroke="color(index)"
                            stroke-width="3"
                            :stroke-dasharray="dash(index)"
                        />
                    </svg>
                    {{ t(`usage.metrics.${key}`) }}
                </button>
            </div>
            <p v-if="empty" class="text-sm text-muted-foreground">
                {{ t('usage.empty') }}
            </p>
            <details class="text-sm">
                <summary
                    class="cursor-pointer rounded py-2 font-medium focus-visible:outline-2 focus-visible:outline-ring"
                >
                    {{ t('usage.data') }}
                </summary>
                <div
                    class="max-h-80 overflow-auto"
                    tabindex="0"
                    role="region"
                    :aria-labelledby="`usage-title-${id}`"
                >
                    <table class="w-full text-left tabular-nums">
                        <caption class="sr-only">
                            {{
                                t(title)
                            }}
                        </caption>
                        <thead class="sticky top-0 bg-card">
                            <tr>
                                <th scope="col" class="p-2">
                                    {{ t('usage.date') }}
                                </th>
                                <th
                                    v-for="key in visible"
                                    :key="key"
                                    scope="col"
                                    class="p-2 text-right"
                                >
                                    {{ t(`usage.metrics.${key}`) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="day in daily"
                                :key="day.date"
                                class="border-t"
                            >
                                <th
                                    scope="row"
                                    class="p-2 font-normal whitespace-nowrap"
                                >
                                    {{ date(day.date) }}
                                </th>
                                <td
                                    v-for="key in visible"
                                    :key="key"
                                    class="p-2 text-right"
                                >
                                    {{ formatNumber(day[key]) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </CardContent>
    </Card>
</template>
