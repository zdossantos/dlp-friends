<script setup lang="ts">
import EventDetail from '@/components/events/EventDetail.vue';
import EventForm from '@/components/events/EventForm.vue';
import EventParticipantList from '@/components/events/EventParticipantList.vue';
import EventParticipantProfile from '@/components/events/EventParticipantProfile.vue';
import OrganizerRegistrations from '@/components/events/OrganizerRegistrations.vue';
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
        <EventParticipantList
            :event-id="panel.event.id"
            :context="context"
            :participants="panel.event.participants ?? []"
        />
    </section>
    <section
        v-else-if="panel.kind === 'participant-profile'"
        class="space-y-4 pt-2"
    >
        <EventParticipantProfile
            :profile="panel.profile"
            :back-href="
                participantsIndex(panel.event.id, { query: origin }).url
            "
        />
    </section>
    <section v-else-if="panel.kind === 'registrations'" class="space-y-4 pt-2">
        <OrganizerRegistrations
            :registrations="panel.event.registrations ?? []"
            :context="context"
        />
    </section>
</template>
