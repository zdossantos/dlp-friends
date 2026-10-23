<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import { parisLocalFormValue } from '@/lib/eventState';
import type { TripDetail } from '@/types/trip';
const props = defineProps<{ trip?: TripDetail }>();
const emit = defineEmits<{ saved: [] }>();
const { t } = useTranslations();
const form = useForm({
    transport: props.trip?.transport ?? 'car',
    direction: props.trip?.direction ?? 'outbound',
    general_departure: props.trip?.general_departure ?? '',
    destination: props.trip?.destination ?? '',
    departs_at: parisLocalFormValue(
        props.trip?.departs_at ??
            new Date(Date.now() + 15 * 60000).toISOString(),
    ),
    arrives_at: props.trip?.arrives_at
        ? parisLocalFormValue(props.trip.arrives_at)
        : '',
    meeting_point: props.trip?.meeting_point ?? '',
    audience: props.trip?.audience ?? 'matches',
    car_seats: props.trip?.car_seats ?? 1,
    confirm_members: false,
});
const share = ref(false);
const locked = computed(() => Boolean(props.trip?.first_accepted_at));
const choices = {
    transport: ['car', 'train', 'rer', 'bus'],
    direction: ['outbound', 'return'],
    audience: ['matches', 'members'],
} as const;
function submit(confirmed = false) {
    if (
        form.audience === 'members' &&
        props.trip?.audience !== 'members' &&
        !confirmed
    ) {
        share.value = true;

        return;
    }

    form.confirm_members = confirmed;
    form.transform((data) =>
        data.transport === 'car' ? data : { ...data, car_seats: null },
    );
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            share.value = false;
            emit('saved');
        },
        onError: () => {
            share.value = false;
        },
    };

    if (props.trip) {
        form.patch(`/trips/${props.trip.id}`, options);
    } else {
        form.post('/trips', options);
    }
}
</script>
<template>
    <form class="min-w-0 space-y-5" @submit.prevent="submit()">
        <p v-if="locked" class="text-sm text-muted-foreground">
            {{ t('trips.locked') }}
        </p>
        <div v-for="(values, field) in choices" :key="field" class="space-y-2">
            <Label :for="field">{{ t(`trips.fields.${field}`) }}</Label>
            <Select
                v-model="form[field]"
                :disabled="locked && field !== 'audience'"
                ><SelectTrigger :id="field" class="w-full"
                    ><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem
                        v-for="value in values"
                        :key="value"
                        :value="value"
                        :data-value="value"
                        >{{ t(`trips.${field}.${value}`) }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <InputError :message="form.errors[field]" />
        </div>
        <div
            v-for="field in [
                'general_departure',
                'destination',
                'departs_at',
                'arrives_at',
                'meeting_point',
            ] as const"
            :key="field"
            class="min-w-0 space-y-2"
        >
            <Label :for="field">{{ t(`trips.fields.${field}`) }}</Label>
            <Input
                :id="field"
                v-model="form[field]"
                :type="
                    ['departs_at', 'arrives_at'].includes(field)
                        ? 'datetime-local'
                        : 'text'
                "
                :min="field === 'arrives_at' ? form.departs_at : undefined"
                :maxlength="field === 'meeting_point' ? 255 : 160"
                :disabled="locked && field !== 'meeting_point'"
                :required="field !== 'arrives_at'"
                class="w-full min-w-0"
                :aria-invalid="Boolean(form.errors[field])"
                :aria-describedby="`${field}-error`"
            />
            <p
                v-if="field === 'arrives_at'"
                class="text-sm text-muted-foreground"
            >
                {{ t('trips.arrival_help') }}
            </p>
            <InputError :id="`${field}-error`" :message="form.errors[field]" />
        </div>
        <div v-if="form.transport === 'car'" class="space-y-2">
            <Label for="car_seats">{{ t('trips.fields.car_seats') }}</Label
            ><Input
                id="car_seats"
                v-model="form.car_seats"
                type="number"
                min="1"
                max="65535"
                required
            /><InputError :message="form.errors.car_seats" />
        </div>
        <InputError :message="form.errors.confirm_members" />
        <Button
            type="submit"
            :disabled="form.processing"
            data-test="trip-submit"
            >{{ t('trips.save') }}</Button
        >
    </form>
    <ResponsiveConfirmation
        v-model:open="share"
        test-prefix="trip-share"
        :title="t('trips.share_title')"
        :description="t('trips.share_description')"
        :confirm-label="t('trips.share')"
        destructive
        :busy="form.processing"
        @confirm="submit(true)"
    />
</template>
