<script setup lang="ts">
import { Eye, LockKeyhole, MapPin, Users } from '@lucide/vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { OwnVisit, VisitDay, VisitsVisibility } from '@/types/visit';
const props = defineProps<{
    day: VisitDay;
    ownVisits: OwnVisit[];
    visibility: VisitsVisibility;
}>();
const emit = defineEmits<{
    edit: [visit: OwnVisit];
    attendees: [date: string, event: Event];
    member: [id: number, date: string];
}>();
const { t, formatDate } = useTranslations();
</script>
<template>
    <section
        class="space-y-4"
        :aria-label="formatDate(day.date + 'T12:00:00', { dateStyle: 'full' })"
    >
        <div
            v-for="visit in props.ownVisits.filter((v) =>
                day.ownVisitIds.includes(v.id),
            )"
            :key="visit.id"
            class="rounded-xl bg-secondary p-4 text-secondary-foreground"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="flex items-center gap-2 font-semibold">
                    <MapPin class="size-5" aria-hidden="true" />{{
                        t('visits.your_visit')
                    }}
                </h3>
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11"
                    :data-test="`visit-edit-${visit.id}-${day.date}`"
                    @click="emit('edit', visit)"
                    >{{ t('visits.edit') }}</Button
                >
            </div>
            <p class="text-sm">
                {{
                    formatDate(visit.starts_on + 'T12:00:00', {
                        dateStyle: 'medium',
                    })
                }}
                —
                {{
                    formatDate(visit.ends_on + 'T12:00:00', {
                        dateStyle: 'medium',
                    })
                }}
            </p>
            <p class="mt-2 flex items-center gap-2 text-sm">
                <component
                    :is="
                        visibility === 'hidden'
                            ? LockKeyhole
                            : visibility === 'matches'
                              ? Users
                              : Eye
                    "
                    class="size-4"
                    aria-hidden="true"
                />{{ t(`visits.visibility.${visibility}`) }}
            </p>
        </div>
        <div class="space-y-2">
            <h3 class="font-semibold">
                {{ t('visits.also_present', { count: day.otherCount }) }}
            </h3>
            <p v-if="!day.otherCount" class="text-sm text-muted-foreground">
                {{ t('visits.no_people') }}
            </p>
            <ul v-else class="divide-y divide-border">
                <li v-for="member in day.preview" :key="member.id">
                    <button
                        type="button"
                        class="flex min-h-14 w-full items-center gap-3 rounded-lg py-2 text-left focus-visible:ring-2 focus-visible:ring-ring"
                        @click="emit('member', member.id, day.date)"
                    >
                        <UserAvatar
                            :avatar="member.avatar"
                            :display-name="member.display_name"
                        /><span class="min-w-0 flex-1 truncate font-medium">{{
                            member.display_name
                        }}</span
                        ><span
                            v-if="member.is_match"
                            class="text-xs text-muted-foreground"
                            >{{ t('visits.match') }}</span
                        >
                    </button>
                </li>
            </ul>
            <Button
                v-if="day.otherCount"
                variant="outline"
                class="min-h-11 w-full"
                :id="`visit-opener-${day.date}`"
                data-test="visit-all-attendees"
                @click="emit('attendees', day.date, $event)"
                >{{ t('visits.see_people', { count: day.otherCount }) }}</Button
            >
        </div>
    </section>
</template>
