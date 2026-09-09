<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { availableEventActions } from '@/lib/eventState';
import { destroy, store } from '@/routes/events/registrations';
import type { EventDetail } from '@/types/event';

const props = defineProps<{ event: EventDetail }>();
const { t } = useTranslations();
const busy = ref(false);
const actions = computed(() =>
    availableEventActions({
        role: props.event.isOrganizer ? 'organizer' : 'member',
        status: props.event.registrationStatus,
        started: props.event.isStarted,
        cancelled: props.event.isCancelled,
    }),
);
function register(): void {
    busy.value = true;
    router.post(
        store(props.event.id).url,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}
function withdraw(): void {
    if (!window.confirm(t('events.confirmations.withdraw_title'))) {
        return;
    }

    busy.value = true;
    router.delete(destroy(props.event.id).url, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
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
        <Button
            v-if="actions.includes('withdraw')"
            variant="outline"
            :disabled="busy"
            data-test="event-withdraw"
            @click="withdraw"
            >{{ t('events.actions.withdraw') }}</Button
        >
    </div>
</template>
