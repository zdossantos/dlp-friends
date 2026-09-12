<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { parisLocalFormValue } from '@/lib/eventState';
import type { EventDetail } from '@/types/event';

const props = defineProps<{
    event?: EventDetail;
    action: string;
    method: 'post' | 'patch';
    submitLabel: string;
}>();
const { t } = useTranslations();
const startsAt = props.event ? parisLocalFormValue(props.event.startsAt) : '';
const description = ref(props.event?.description ?? '');
const registrationMode = ref(props.event?.registrationMode ?? 'automatic');
</script>

<template>
    <Form
        :action="action"
        :method="method"
        v-slot="{ errors, processing }"
        class="space-y-5"
    >
        <div class="space-y-2">
            <Label for="title">{{ t('events.fields.title') }}</Label
            ><Input
                id="title"
                name="title"
                required
                maxlength="120"
                :default-value="event?.title"
            /><InputError :message="errors.title" />
        </div>
        <div class="space-y-2">
            <Label for="description">{{ t('events.fields.description') }}</Label
            ><Textarea
                id="description"
                name="description"
                required
                maxlength="2000"
                class="min-h-28"
                v-model="description"
            /><InputError :message="errors.description" />
        </div>
        <div class="space-y-2">
            <Label for="general_location">{{
                t('events.fields.general_location')
            }}</Label
            ><Input
                id="general_location"
                name="general_location"
                required
                maxlength="160"
                :default-value="event?.generalLocation"
            /><InputError :message="errors.general_location" />
        </div>
        <div class="space-y-2">
            <Label for="detailed_location">{{
                t('events.fields.detailed_location')
            }}</Label
            ><Input
                id="detailed_location"
                name="detailed_location"
                required
                maxlength="255"
                :default-value="event?.detailedLocation"
            /><InputError :message="errors.detailed_location" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="starts_at">{{ t('events.fields.starts_at') }}</Label
                ><Input
                    id="starts_at"
                    name="starts_at"
                    type="datetime-local"
                    required
                    :default-value="startsAt"
                /><InputError :message="errors.starts_at" />
            </div>
            <div class="space-y-2">
                <Label for="capacity">{{ t('events.fields.capacity') }}</Label
                ><Input
                    id="capacity"
                    name="capacity"
                    type="number"
                    min="1"
                    max="100"
                    required
                    :default-value="event?.capacity ?? 4"
                /><InputError :message="errors.capacity" />
            </div>
        </div>
        <div class="space-y-2">
            <Label for="registration_mode">{{
                t('events.fields.registration_mode')
            }}</Label
            ><NativeSelect
                id="registration_mode"
                name="registration_mode"
                class="w-full"
                v-model="registrationMode"
            >
                <option value="automatic">
                    {{ t('events.modes.automatic') }}
                </option>
                <option value="manual">
                    {{ t('events.modes.manual') }}
                </option></NativeSelect
            ><InputError :message="errors.registration_mode" />
        </div>
        <InputError :message="errors.event" />
        <Button type="submit" :disabled="processing" data-test="event-submit"
            ><Spinner v-if="processing" />{{ submitLabel }}</Button
        >
    </Form>
</template>
