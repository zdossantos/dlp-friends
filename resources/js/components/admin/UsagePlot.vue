<script setup lang="ts">
import { LineChart, BarChart, PieChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { init, use } from 'echarts/core';
import type { ECharts, EChartsCoreOption } from 'echarts/core';
import { SVGRenderer } from 'echarts/renderers';
import { onMounted, onBeforeUnmount, useTemplateRef, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

use([
    LineChart,
    BarChart,
    PieChart,
    GridComponent,
    TooltipComponent,
    SVGRenderer,
]);
const props = defineProps<{ option: EChartsCoreOption }>();
const element = useTemplateRef<HTMLDivElement>('plot');
const { resolvedAppearance } = useAppearance();
let chart: ECharts | undefined;
let observer: ResizeObserver | undefined;
function draw() {
    if (!chart || !element.value) {
        return;
    }

    const style = getComputedStyle(element.value);
    chart.setOption(
        {
            ...props.option,
            animation: false,
            textStyle: { color: style.color, fontFamily: style.fontFamily },
        },
        true,
    );
    chart.setOption({ tooltip: { confine: true, renderMode: 'richText' } });

    if (props.option.xAxis) {
        const axis = {
            axisLabel: { color: style.color },
            axisLine: { lineStyle: { color: style.color } },
        };
        chart.setOption({ xAxis: axis, yAxis: axis });
    }
}
onMounted(() => {
    chart = init(element.value!, undefined, { renderer: 'svg' });
    observer = new ResizeObserver(() => chart?.resize());
    observer.observe(element.value!);
    draw();
});
watch(() => [props.option, resolvedAppearance.value], draw, { flush: 'post' });
onBeforeUnmount(() => {
    observer?.disconnect();
    chart?.dispose();
});
</script>
<template>
    <div
        ref="plot"
        class="h-72 w-full min-w-0 text-foreground"
        aria-hidden="true"
    />
</template>
