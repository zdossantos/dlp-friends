<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    DateRangePickerRoot,
    DateRangePickerTrigger,
    DateRangePickerContent,
    DateRangePickerCalendar,
    DateRangePickerHeader,
    DateRangePickerHeading,
    DateRangePickerPrev,
    DateRangePickerNext,
    DateRangePickerGrid,
    DateRangePickerGridHead,
    DateRangePickerGridBody,
    DateRangePickerGridRow,
    DateRangePickerHeadCell,
    DateRangePickerCell,
    DateRangePickerCellTrigger,
} from 'reka-ui';
import type { DateRange } from 'reka-ui';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{
    from: string;
    to: string;
    max: string;
    disabled: boolean;
}>();
const emit = defineEmits<{ select: [from: string, to: string] }>();
const { t, locale, formatDate } = useTranslations();
const range = computed(() => ({
    start: parseDate(props.from),
    end: parseDate(props.to),
}));
const label = computed(() =>
    [props.from, props.to]
        .map((value) =>
            formatDate(`${value}T00:00:00Z`, {
                dateStyle: 'medium',
                timeZone: 'UTC',
            }),
        )
        .join(' – '),
);
function select(value: DateRange) {
    if (value.start && value.end) {
        emit('select', value.start.toString(), value.end.toString());
    }
}
</script>
<template>
    <DateRangePickerRoot
        :model-value="range"
        :locale="locale"
        :min-value="parseDate('2000-01-01')"
        :max-value="parseDate(max)"
        :week-starts-on="1"
        :disabled="disabled"
        close-on-select
        @update:model-value="select"
    >
        <DateRangePickerTrigger as-child>
            <Button
                type="button"
                variant="outline"
                class="h-auto min-h-11 w-full justify-start whitespace-normal sm:w-auto"
                data-test="usage-range-trigger"
                :aria-label="t('usage.choose_range')"
                ><CalendarDays class="size-4 shrink-0" aria-hidden="true" />{{
                    label
                }}</Button
            >
        </DateRangePickerTrigger>
        <DateRangePickerContent
            align="start"
            :side-offset="6"
            class="z-50 max-w-[calc(100vw-2rem)] rounded-xl border bg-popover p-3 text-popover-foreground shadow-md"
            data-test="usage-calendar"
        >
            <p class="mb-3 max-w-64 text-sm text-muted-foreground">
                {{ t('usage.range_help') }}
            </p>
            <DateRangePickerCalendar v-slot="{ grid, weekDays }">
                <DateRangePickerHeader
                    class="mb-3 flex items-center justify-between gap-2"
                >
                    <DateRangePickerPrev
                        as-child
                        :aria-label="t('usage.previous_month')"
                        data-test="usage-prev-month"
                        ><Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="t('usage.previous_month')"
                            ><ChevronLeft class="size-4" /></Button
                    ></DateRangePickerPrev>
                    <DateRangePickerHeading class="text-sm font-semibold" />
                    <DateRangePickerNext
                        as-child
                        :aria-label="t('usage.next_month')"
                        ><Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="t('usage.next_month')"
                            ><ChevronRight class="size-4" /></Button
                    ></DateRangePickerNext>
                </DateRangePickerHeader>
                <DateRangePickerGrid
                    v-for="month in grid"
                    :key="month.value.toString()"
                    class="w-full border-collapse select-none"
                >
                    <DateRangePickerGridHead
                        ><DateRangePickerGridRow
                            ><DateRangePickerHeadCell
                                v-for="weekday in weekDays"
                                :key="weekday"
                                class="pb-2 text-xs font-normal text-muted-foreground"
                                >{{ weekday }}</DateRangePickerHeadCell
                            ></DateRangePickerGridRow
                        ></DateRangePickerGridHead
                    >
                    <DateRangePickerGridBody
                        ><DateRangePickerGridRow
                            v-for="(week, index) in month.rows"
                            :key="index"
                        >
                            <DateRangePickerCell
                                v-for="date in week"
                                :key="date.toString()"
                                :date="date"
                                class="p-0"
                            >
                                <DateRangePickerCellTrigger
                                    :day="date"
                                    :month="month.value"
                                    :data-test="`usage-date-${date}`"
                                    class="size-9 rounded-md text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[highlighted]:bg-accent data-[outside-view]:text-muted-foreground data-[selected]:bg-primary data-[selected]:text-primary-foreground"
                                />
                            </DateRangePickerCell> </DateRangePickerGridRow
                    ></DateRangePickerGridBody>
                </DateRangePickerGrid>
            </DateRangePickerCalendar>
        </DateRangePickerContent>
    </DateRangePickerRoot>
</template>
