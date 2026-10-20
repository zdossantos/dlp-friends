<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { store, update, destroy } from '@/routes/visits';
import type { OwnVisit } from '@/types/visit';
const props = defineProps<{ visit: OwnVisit | null; day: string }>();
const emit = defineEmits<{ saved: [] }>();
const { t } = useTranslations();
const removing = ref(false);
const form = useForm({
    starts_on: props.visit?.starts_on ?? props.day,
    ends_on: props.visit?.ends_on ?? props.day,
});
function submit() {
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    if (props.visit) {
        form.patch(update.url(props.visit.id), options);
    } else {
        form.post(store.url(), options);
    }
}
function remove() {
    if (props.visit) {
        form.delete(destroy.url(props.visit.id), {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    }
}
</script>
<template>
    <form class="space-y-5" @submit.prevent="submit">
        <p class="text-sm text-muted-foreground">{{ t('visits.form_help') }}</p>
        <div
            v-for="field in ['starts_on', 'ends_on'] as const"
            :key="field"
            class="min-w-0 space-y-2"
        >
            <Label :for="field">{{ t(`visits.${field}`) }}</Label>
            <Input
                :id="field"
                v-model="form[field]"
                :name="field"
                type="date"
                required
                class="min-h-11 max-w-full min-w-0"
                :aria-invalid="!!form.errors[field]"
                :aria-describedby="`${field}-error`"
            />
            <InputError :id="`${field}-error`" :message="form.errors[field]" />
        </div>
        <div class="flex flex-wrap justify-between gap-3">
            <Button
                v-if="visit"
                type="button"
                variant="destructive"
                class="min-h-11"
                data-test="visit-delete"
                :disabled="form.processing"
                @click="removing = true"
                >{{ t('visits.delete') }}</Button
            ><Button
                type="submit"
                :disabled="form.processing"
                class="min-h-11"
                data-test="visit-submit"
                >{{ t('visits.save') }}</Button
            >
        </div>
    </form>
    <ResponsiveConfirmation
        :open="removing"
        return-focus="[data-test=visit-delete]"
        :title="t('visits.delete_title')"
        :description="t('visits.delete_description')"
        :confirm-label="t('visits.delete')"
        destructive
        :busy="form.processing"
        test-prefix="visit-confirm"
        @update:open="removing = $event"
        @confirm="remove"
    />
</template>
