<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import EventDetail from '@/components/events/EventDetail.vue';
import EventForm from '@/components/events/EventForm.vue';
import OrganizerRegistrations from '@/components/events/OrganizerRegistrations.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { store, update } from '@/routes/events';
import { index as participantsIndex } from '@/routes/events/participants';
import type { EventPanel, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    panel: EventPanel;
    context: EventWorkspaceContext;
}>();
const { t } = useTranslations();
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
</script>

<template>
    <EventDetail
        v-if="panel.kind === 'detail'"
        :event="panel.event"
        :context="context"
    />
    <EventForm
        v-else-if="panel.kind === 'create'"
        :action="store({ query: origin }).url"
        method="post"
        :submit-label="t('events.create.submit')"
    />
    <EventForm
        v-else-if="panel.kind === 'edit'"
        :event="panel.event"
        :action="update(panel.event.id, { query: origin }).url"
        method="patch"
        :submit-label="t('events.edit.submit')"
    />
    <section v-else-if="panel.kind === 'participants'" class="space-y-3 pt-2">
        <ul class="divide-y divide-border rounded-2xl border border-border">
            <li
                v-for="participant in panel.event.participants"
                :key="participant.id"
                class="p-4 font-medium"
            >
                {{ participant.displayName }}
            </li>
        </ul>
    </section>
    <section
        v-else-if="panel.kind === 'participant-profile'"
        class="space-y-4 pt-2"
    >
        <Button as-child size="icon" variant="outline" class="ml-auto flex">
            <Link
                :href="participantsIndex(panel.event.id, { query: origin })"
                :aria-label="t('events.actions.back')"
            >
                <ArrowLeft class="size-4" />
            </Link>
        </Button>
        <p class="text-xl font-semibold">
            {{ panel.profile.member.display_name }}
        </p>
    </section>
    <OrganizerRegistrations
        v-else-if="panel.kind === 'registrations'"
        :registrations="panel.event.registrations ?? []"
    />
</template>
