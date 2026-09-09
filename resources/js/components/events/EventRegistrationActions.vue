<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EventConfirmationDialog from '@/components/events/EventConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { availableEventActions } from '@/lib/eventState';
import { destroy, store } from '@/routes/events/registrations';
import type { EventDetail, EventWorkspaceContext } from '@/types/event';

const props = defineProps<{
    event: EventDetail;
    context: EventWorkspaceContext;
}>();
const { t } = useTranslations();
const page = usePage();
const busy = ref(false);
const registrationError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;
    return errors?.registration;
});
const origin = props.context === 'mine' ? { origin: 'mine' } : {};
const actions = computed(() =>
    availableEventActions({
        role: props.event.isOrganizer ? 'organizer' : 'member',
        status: props.event.registrationStatus,
        started: props.event.isStarted,
        cancelled: props.event.isCancelled,
        full: props.event.occupiedPlaces >= props.event.capacity,
    }),
);
function register(): void {
    busy.value = true;
    router.post(
        store(props.event.id, { query: origin }).url,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}
function withdraw(): void {
    busy.value = true;
    router.delete(destroy(props.event.id, { query: origin }).url, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap gap-3">
            <Badge
                v-if="!event.isOrganizer && event.registrationStatus"
                variant="secondary"
                data-test="event-registration-status"
                >{{ t(`events.statuses.${event.registrationStatus}`) }}</Badge
            >
            <Button
                v-if="actions.includes('register')"
                :disabled="busy"
                data-test="event-register"
                @click="register"
                >{{ t('events.actions.register') }}</Button
            >
            <EventConfirmationDialog
                v-if="actions.includes('withdraw')"
                :title="t('events.confirmations.withdraw_title')"
                :description="t('events.confirmations.withdraw_description')"
                :confirm-label="t('events.actions.withdraw')"
                :busy="busy"
                destructive
                @confirm="withdraw"
            >
                <Button
                    variant="outline"
                    :disabled="busy"
                    data-test="event-withdraw"
                >
                    {{ t('events.actions.withdraw') }}
                </Button>
            </EventConfirmationDialog>
        </div>
        <InputError
            :message="registrationError"
            data-test="event-registration-error"
        />
    </div>
</template>
