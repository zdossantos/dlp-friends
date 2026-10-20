<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { VisitDay } from '@/types/visit';
const props = defineProps<{
    month: string;
    days: VisitDay[];
    selected: string;
}>();
const emit = defineEmits<{ select: [day: string] }>();
const { t, formatDate } = useTranslations();
const cells = computed(() => {
    const start = new Date(props.month + '-01T12:00:00');
    const offset = (start.getDay() + 6) % 7;
    const length = new Date(
        start.getFullYear(),
        start.getMonth() + 1,
        0,
    ).getDate();

    return [
        ...Array.from({ length: offset }, () => null),
        ...Array.from({ length }, (_, i) => {
            const date = `${props.month}-${String(i + 1).padStart(2, '0')}`;

            return {
                date,
                day: i + 1,
                data: props.days.find((d) => d.date === date),
            };
        }),
    ];
});
</script>
<template>
    <div data-test="visit-calendar" class="-mx-3 space-y-2 sm:mx-0">
        <div class="grid grid-cols-7 text-center text-xs text-muted-foreground">
            <span v-for="i in 7" :key="i" class="py-2">{{
                formatDate(`2026-06-${String(i).padStart(2, '0')}T12:00:00`, {
                    weekday: 'short',
                })
            }}</span>
        </div>
        <div class="grid grid-cols-7 gap-px sm:gap-1">
            <template v-for="(cell, i) in cells" :key="i"
                ><span v-if="!cell" />
                <button
                    v-else
                    type="button"
                    :disabled="!cell.data"
                    :data-test="`visit-day-${cell.date}`"
                    :aria-pressed="selected === cell.date"
                    :aria-label="
                        t('visits.day_label', {
                            date: formatDate(cell.date + 'T12:00:00', {
                                dateStyle: 'full',
                            }),
                            count: cell.data?.otherCount ?? 0,
                            own: cell.data?.ownVisitIds.length
                                ? t('visits.you')
                                : '',
                        })
                    "
                    class="flex min-h-20 min-w-0 flex-col items-center gap-1 rounded-xl border p-1 text-sm tabular-nums focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-40 sm:min-h-24"
                    :class="
                        selected === cell.date
                            ? 'border-primary bg-secondary text-secondary-foreground'
                            : 'border-border bg-card hover:bg-muted'
                    "
                    @click="emit('select', cell.date)"
                >
                    <span
                        class="font-medium"
                        :class="
                            cell.data?.isToday
                                ? 'underline underline-offset-4'
                                : ''
                        "
                        >{{ cell.day }}</span
                    ><span
                        v-if="cell.data?.ownVisitIds.length"
                        class="flex items-center gap-1 text-[10px] font-semibold"
                        ><span
                            class="size-1.5 rounded-full bg-current"
                            aria-hidden="true"
                        />{{ t('visits.you') }}</span
                    ><span
                        v-if="cell.data?.otherCount"
                        class="text-xs text-muted-foreground"
                        >{{
                            cell.data.otherCount > 99
                                ? t('visits.many')
                                : cell.data.otherCount
                        }}</span
                    >
                </button>
            </template>
        </div>
    </div>
</template>
