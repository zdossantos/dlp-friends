<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import type { TripDeparture } from '@/types/trip';
const props = defineProps<{ departure: TripDeparture }>();
const { t } = useTranslations();
const open = ref(false);
const confirm = ref(false);
const form = useForm({ details: '', confirmed: true });
function send() {
    form.post(`/trip-departures/${props.departure.id}/reports`, {
        preserveScroll: true,
        onSuccess: () => {
            confirm.value = false;
            open.value = false;
            form.reset();
        },
        onError: () => {
            confirm.value = false;
        },
    });
}
</script>
<template>
    <Button
        v-if="departure.can_report"
        variant="outline"
        :data-test="`trip-report-${departure.id}`"
        @click="open = true"
        >{{ t('trips.report') }}</Button
    >
    <ResponsivePanel
        v-model:open="open"
        :title="t('trips.report')"
        :description="t('trips.report_help')"
        ><form class="space-y-4" @submit.prevent="confirm = true">
            <Label for="trip-report-details">{{
                t('trips.report_details')
            }}</Label
            ><Textarea
                id="trip-report-details"
                v-model="form.details"
                maxlength="1000"
                required
            /><InputError :message="form.errors.details" /><InputError
                :message="form.errors.confirmed"
            /><Button data-test="trip-report-send" type="submit">{{
                t('trips.report_send')
            }}</Button>
        </form></ResponsivePanel
    >
    <ResponsiveConfirmation
        v-model:open="confirm"
        test-prefix="trip-report"
        :title="t('trips.report')"
        :description="t('trips.report_help')"
        :confirm-label="t('trips.report_send')"
        :busy="form.processing"
        @confirm="send"
    />
</template>
