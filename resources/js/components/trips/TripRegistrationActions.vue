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
import type { TripDetail, TripRegistration } from '@/types/trip';
const props = defineProps<{
    trip: TripDetail;
    registration?: TripRegistration;
}>();
const { t } = useTranslations();
const action = ref<'refuse' | 'remove' | 'leave' | 'cancel' | null>(null);
const confirm = ref(false);
const form = useForm({ reason: '', confirmed: false, accept: false });
function open(kind: typeof action.value) {
    form.reset();
    form.clearErrors();
    action.value = kind;
}
function send() {
    const kind = action.value;

    if (!kind) {
        return;
    }

    form.confirmed = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirm.value = false;
            action.value = null;
        },
        onError: () => {
            confirm.value = false;
        },
    };

    if (kind === 'refuse') {
        form.patch(`/trip-registrations/${props.registration?.id}`, options);
    }

    if (kind === 'remove') {
        form.delete(`/trip-registrations/${props.registration?.id}`, options);
    }

    if (kind === 'leave') {
        form.delete(`/trips/${props.trip.id}/registrations`, options);
    }

    if (kind === 'cancel') {
        form.patch(`/trips/${props.trip.id}/cancel`, options);
    }
}
function accept() {
    form.transform((data) => ({ ...data, accept: true })).patch(
        `/trip-registrations/${props.registration?.id}`,
        {
            preserveScroll: true,
            onFinish: () =>
                form.transform((data) => ({ ...data, accept: false })),
        },
    );
}
function request() {
    form.post(`/trips/${props.trip.id}/registrations`, {
        preserveScroll: true,
    });
}
function withdraw() {
    form.delete(`/trips/${props.trip.id}/registrations`, {
        preserveScroll: true,
    });
}
</script>
<template>
    <div
        class="flex flex-wrap gap-2 [&>button]:min-h-11 [&>button]:flex-1 [&>button]:whitespace-normal"
    >
        <template v-if="registration">
            <template v-if="trip.can_edit && registration.status === 'pending'"
                ><Button
                    :data-test="`trip-accept-${registration.id}`"
                    :disabled="form.processing || trip.remaining_places === 0"
                    @click="accept"
                    >{{ t('trips.accept') }}</Button
                ><Button
                    variant="outline"
                    :data-test="`trip-refuse-${registration.id}`"
                    @click="open('refuse')"
                    >{{ t('trips.refuse') }}</Button
                ></template
            >
            <Button
                v-if="trip.can_edit && registration.status === 'accepted'"
                variant="destructive"
                :data-test="`trip-remove-${registration.id}`"
                @click="open('remove')"
                >{{ t('trips.remove') }}</Button
            >
        </template>
        <template v-else>
            <Button
                v-if="
                    trip.can_request &&
                    !['pending', 'accepted'].includes(
                        trip.registration?.status ?? '',
                    )
                "
                data-test="trip-request"
                :disabled="form.processing"
                @click="request"
                >{{ t('trips.request') }}</Button
            >
            <Button
                v-if="
                    trip.registration?.status === 'pending' &&
                    !trip.cancelled_at
                "
                variant="outline"
                :disabled="form.processing"
                @click="withdraw"
                >{{ t('trips.withdraw') }}</Button
            >
            <Button
                v-if="trip.registration?.status === 'accepted'"
                variant="destructive"
                @click="open('leave')"
                >{{ t('trips.leave') }}</Button
            >
            <Button
                v-if="trip.can_edit"
                variant="outline"
                class="border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                data-test="trip-cancel"
                @click="open('cancel')"
                >{{ t('trips.cancel') }}</Button
            >
        </template>
    </div>
    <div v-if="Object.keys(form.errors).length && !action" role="alert">
        <InputError
            v-for="(error, key) in form.errors"
            :key="key"
            :message="error"
        />
    </div>
    <ResponsivePanel
        :open="action !== null"
        :title="t(`trips.${action ?? 'confirm_title'}`)"
        :description="t('trips.reason_help')"
        @update:open="
            (value) => {
                if (!value) action = null;
            }
        "
    >
        <form class="space-y-4" @submit.prevent="confirm = true">
            <Label for="trip-reason">{{ t('trips.reason') }}</Label
            ><Textarea
                id="trip-reason"
                v-model="form.reason"
                required
                maxlength="1000"
                aria-describedby="trip-reason-error"
            /><InputError
                id="trip-reason-error"
                :message="form.errors.reason"
            /><InputError :message="form.errors.confirmed" /><Button
                type="submit"
                data-test="trip-action-submit"
                >{{ t('trips.confirm') }}</Button
            >
        </form>
    </ResponsivePanel>
    <ResponsiveConfirmation
        v-model:open="confirm"
        test-prefix="trip-danger"
        :title="t('trips.confirm_title')"
        :description="t('trips.confirm_description')"
        :confirm-label="t('trips.confirm')"
        destructive
        :busy="form.processing"
        @confirm="send"
    />
</template>
