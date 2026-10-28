<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import UsagePlot from './UsagePlot.vue';
const props = defineProps<{
    id: string;
    title: TranslationKey;
    description: TranslationKey;
    colors: string[];
    values: { label: TranslationKey; value: number }[];
}>();
const { t, formatNumber } = useTranslations();
const total = computed(() =>
    props.values.reduce((sum, item) => sum + item.value, 0),
);
const option = computed(() => ({
    tooltip: {
        trigger: 'item',
        valueFormatter: (value: number) => formatNumber(value),
    },
    series: [
        {
            type: 'pie',
            radius: ['48%', '75%'],
            label: { show: false },
            showEmptyCircle: true,
            data: props.values.map((item, index) => ({
                name: t(item.label),
                value: item.value,
                itemStyle: { color: props.colors[index] },
            })),
        },
    ],
}));
</script>
<template>
    <Card :data-test="`usage-donut-${id}`" class="min-w-0">
        <CardHeader
            ><CardTitle>{{ t(title) }}</CardTitle
            ><CardDescription>{{ t(description) }}</CardDescription></CardHeader
        >
        <CardContent>
            <UsagePlot v-if="total" :option="option" />
            <p v-else class="py-8 text-sm text-muted-foreground">
                {{ t('usage.empty') }}
            </p>
            <dl class="space-y-3 text-sm">
                <div
                    v-for="(item, index) in values"
                    :key="item.label"
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <dt class="flex items-center gap-2">
                        <span
                            class="size-3 rounded-full"
                            :style="{ background: colors[index] }"
                            aria-hidden="true"
                        />{{ t(item.label) }}
                    </dt>
                    <dd class="font-semibold tabular-nums">
                        {{ formatNumber(item.value) }} ·
                        {{
                            formatNumber(total ? item.value / total : 0, {
                                style: 'percent',
                                maximumFractionDigits: 1,
                            })
                        }}
                    </dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
