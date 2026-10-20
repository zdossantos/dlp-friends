<script setup lang="ts">
import { computed } from 'vue';
import VisitDayDetail from '@/components/visits/VisitDayDetail.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { OwnVisit, VisitDay, VisitsVisibility } from '@/types/visit';
const props = defineProps<{
    days: VisitDay[];
    ownVisits: OwnVisit[];
    visibility: VisitsVisibility;
}>();
const emit = defineEmits<{
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
        <p
            v-if="!populated.length"
            class="rounded-2xl border border-dashed border-border p-8 text-center text-muted-foreground"
        >
            {{ t('visits.empty') }}
        </p>
        <article v-for="day in populated" :key="day.date" class="space-y-3">
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
