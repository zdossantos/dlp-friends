<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import VisitDayDetail from '@/components/visits/VisitDayDetail.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { OwnVisit, VisitDay, VisitsVisibility } from '@/types/visit';
const props = defineProps<{
    days: VisitDay[];
    ownVisits: OwnVisit[];
    visibility: VisitsVisibility;
    filtered: boolean;
}>();
const emit = defineEmits<{
    'clear-filters': [];
    edit: [visit: OwnVisit];
    attendees: [date: string, event: Event];
    member: [id: number];
}>();
const { t, formatDate } = useTranslations();
const populated = computed(() =>
    props.days.filter((day) => day.otherCount || day.ownVisitIds.length),
);
</script>
<template>
    <div data-test="visit-agenda" class="space-y-8">
        <div
            v-if="!populated.length"
            class="rounded-2xl border border-dashed border-border p-8 text-center text-muted-foreground"
        >
            <p>{{ t(filtered ? 'visits.no_results' : 'visits.empty') }}</p>
            <Button
                v-if="filtered"
                class="mt-4 min-h-11"
                variant="outline"
                data-test="visit-clear-filters"
                @click="emit('clear-filters')"
                >{{ t('visits.clear_filters') }}</Button
            >
        </div>
        <article
            v-for="day in populated"
            :key="day.date"
            :data-test="`visit-agenda-day-${day.date}`"
            class="space-y-3 rounded-xl"
            :class="
                day.ownVisitIds.length
                    ? 'border border-primary/40 bg-primary/5 p-3 sm:p-4'
                    : ''
            "
        >
            <h2 class="border-b border-border pb-3 text-lg font-semibold">
                {{
                    formatDate(day.date + 'T12:00:00', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    })
                }}<span
                    v-if="day.isToday"
                    class="ml-2 text-sm text-muted-foreground"
                    >{{ t('visits.today') }}</span
                >
            </h2>
            <VisitDayDetail
                :day="day"
                :own-visits="ownVisits"
                :visibility="visibility"
                @edit="emit('edit', $event)"
                @attendees="(date, event) => emit('attendees', date, event)"
                @member="emit('member', $event)"
            />
        </article>
    </div>
</template>
