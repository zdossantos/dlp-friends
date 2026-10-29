<script setup lang="ts">
import { computed } from 'vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import EventPanelContent from '@/components/events/EventPanelContent.vue';
import { useEventWorkspaceQuery } from '@/composables/useEventWorkspaceQuery';
import { useTranslations } from '@/composables/useTranslations';
import { restoreEventWorkspaceScroll } from '@/lib/eventWorkspaceScroll';
import { show } from '@/routes/events';
import { index as participantsIndex } from '@/routes/events/participants';
import type { EventPanel, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    panel: EventPanel | null;
    context: EventWorkspaceContext;
    closeHref: string;
}>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { t } = useTranslations();
const origin = useEventWorkspaceQuery(() => props.context);
const panelTitle = computed(() => {
    if (!props.panel || props.panel.kind === 'participant-profile') {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.title`);
});
const panelDescription = computed(() => {
    if (!props.panel || props.panel.kind === 'participant-profile') {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.description`);
});
const panelBackHref = computed(() => {
    if (props.panel?.kind === 'participant-profile') {
        return participantsIndex(props.panel.event.id, {
            query: origin.value,
        }).url;
    }

    if (props.panel?.kind === 'registrations' || props.panel?.kind === 'chat') {
        return show(props.panel.event.id, {
            query: origin.value,
        }).url;
    }

    return null;
});
const panelBackDataTest = computed(() => {
    if (props.panel?.kind === 'participant-profile') {
        return 'participant-profile-back';
    }

    if (props.panel?.kind === 'registrations') {
        return 'event-registrations-back';
    }

    if (props.panel?.kind === 'chat') {
        return 'event-chat-back';
    }

    return undefined;
});
const accessiblePanelTitle = computed(() => {
    if (!props.panel) {
        return null;
    }

    return t(`events.panels.${props.panel.kind}.title`);
});
const accessiblePanelDescription = computed(() => {
    if (!props.panel) {
        return null;
    }

    return t(`events.panels.${props.panel.kind}.description`);
});
</script>

<template>
    <ResponsivePanel
        test-id="event-panel"
        @restore-scroll="restoreEventWorkspaceScroll"
        :open="panel !== null"
        :title="panelTitle"
        :description="panelDescription"
        :a11y-heading="accessiblePanelTitle"
        :a11y-summary="accessiblePanelDescription"
        :back-href="panelBackHref"
        :preserve-state="context === 'conversations'"
        :only="context === 'conversations' ? ['panel'] : undefined"
        :back-label="t('events.actions.back')"
        :back-data-test="panelBackDataTest"
        :full-bleed="
            panel?.kind === 'participant-profile' || panel?.kind === 'chat'
        "
        :fill-height="panel?.kind === 'chat'"
        :back-in-header="panel?.kind === 'chat'"
        @update:open="emit('update:open', $event)"
    >
        <EventPanelContent
            v-if="panel"
            :panel="panel"
            :context="context"
            :close-href="closeHref"
        />
    </ResponsivePanel>
</template>
