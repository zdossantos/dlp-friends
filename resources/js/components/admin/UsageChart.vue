<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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

const props = defineProps<{
    id: string;
    title: TranslationKey;
    description: TranslationKey;
    daily: UsageDay[];
    series: UsageMetric[];
}>();
const { t, formatDate, formatNumber } = useTranslations();
const selected = ref(props.daily.length - 1);
const hidden = ref<UsageMetric[]>([]);
watch(
    () => props.daily,
    () => {
        selected.value = props.daily.length - 1;
    },
);
const visible = computed(() =>
    props.series.filter((key) => !hidden.value.includes(key)),
);
const maximum = computed(() =>
    Math.max(
        1,
        ...props.daily.flatMap((day) => visible.value.map((key) => day[key])),
    ),
);
const current = computed(() => props.daily[selected.value] ?? props.daily[0]);
const empty = computed(() =>
    props.daily.every((day) => props.series.every((key) => day[key] === 0)),
);
const x = (index: number) =>
    48 + (index / Math.max(1, props.daily.length - 1)) * 570;
const y = (value: number) => 182 - (value / maximum.value) * 156;
const points = (key: UsageMetric) =>
    props.daily.map((day, index) => `${x(index)},${y(day[key])}`).join(' ');
const color = (index: number) => `var(--chart-${index + 1})`;
const dash = (index: number) => ['', '8 4', '2 4'][index % 3];
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
function inspect(event: PointerEvent) {
    const bounds = (
        event.currentTarget as SVGSVGElement
    ).getBoundingClientRect();
    const position =
        (((event.clientX - bounds.left) / bounds.width) * 640 - 48) / 570;
    selected.value = Math.max(
        0,
        Math.min(
            props.daily.length - 1,
            Math.round(position * (props.daily.length - 1)),
        ),
    );
}
</script>

<template>
    <Card class="min-w-0" :data-test="`usage-chart-${id}`">
        <CardHeader>
            <CardTitle :id="`usage-title-${id}`">{{ t(title) }}</CardTitle>
            <CardDescription>{{ t(description) }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
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
            <svg
                viewBox="0 0 640 220"
                class="w-full text-muted-foreground"
                aria-hidden="true"
                @pointermove="inspect"
            >
                <g v-for="fraction in [0, 0.5, 1]" :key="fraction">
                    <line
                        x1="48"
                        x2="618"
                        :y1="y(maximum * fraction)"
                        :y2="y(maximum * fraction)"
                        stroke="currentColor"
                        stroke-opacity="0.18"
                    />
                    <text
                        x="40"
                        :y="y(maximum * fraction) + 4"
                        text-anchor="end"
                        fill="currentColor"
                        font-size="12"
                    >
                        {{
                            formatNumber(maximum * fraction, {
                                maximumFractionDigits: 1,
                            })
                        }}
                    </text>
                </g>
                <g v-for="(key, index) in series" :key="key">
                    <polyline
                        v-if="visible.includes(key)"
                        :points="points(key)"
                        fill="none"
                        :stroke="color(index)"
                        stroke-width="2.5"
                        :stroke-dasharray="dash(index)"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-if="visible.includes(key) && current"
                        :cx="x(selected)"
                        :cy="y(current[key])"
                        r="4"
                        :fill="color(index)"
                    />
                </g>
                <line
                    :x1="x(selected)"
                    :x2="x(selected)"
                    y1="26"
                    y2="182"
                    stroke="currentColor"
                    stroke-dasharray="3 4"
                />
                <text
                    v-if="daily[0]"
                    x="48"
                    y="210"
                    fill="currentColor"
                    font-size="12"
                >
                    {{ date(daily[0].date) }}
                </text>
                <text
                    v-if="daily.length > 1 && daily.at(-1)"
                    x="618"
                    y="210"
                    text-anchor="end"
                    fill="currentColor"
                    font-size="12"
                >
                    {{ date(daily.at(-1)!.date) }}
                </text>
            </svg>
            <div
                v-if="current"
                class="space-y-2 rounded-md bg-muted/50 p-3 text-sm"
            >
                <label :for="`usage-day-${id}`" class="block font-medium"
                    >{{ t('usage.inspect') }} · {{ date(current.date) }}</label
                >
                <input
                    :id="`usage-day-${id}`"
                    v-model.number="selected"
                    type="range"
                    min="0"
                    :max="daily.length - 1"
                    step="1"
                    :aria-valuetext="date(current.date)"
                    class="h-8 w-full accent-primary"
                />
                <dl class="flex flex-wrap gap-x-5 gap-y-2">
                    <div v-for="key in visible" :key="key">
                        <dt class="inline text-muted-foreground">
                            {{ t(`usage.metrics.${key}`) }}:
                        </dt>
                        <dd class="inline font-semibold tabular-nums">
                            {{ formatNumber(current[key]) }}
                        </dd>
                    </div>
                </dl>
            </div>
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
