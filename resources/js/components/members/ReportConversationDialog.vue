<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/conversations/reports';
const props = defineProps<{
    conversationId: number;
    canBlock: boolean;
}>();
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();
const open = ref(false);
const reasons = [
    'harassment',
    'discrimination',
    'sexual_content',
    'threats',
    'spam',
    'other',
] as const;
const form = useForm({
    reason: '',
    details: '',
    block: props.canBlock,
    confirmed: true,
});
function submit(): void {
    form.post(store(props.conversationId).url, {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>
<template>
    <component :is="Modal.Root" v-model:open="open">
        <component :is="Modal.Trigger" as-child
            ><Button variant="outline" data-test="report-conversation-trigger"
                ><Flag class="size-4" aria-hidden="true" />{{
                    t('moderation.report_trigger')
                }}</Button
            ></component
        >
        <component
            :is="Modal.Content"
            :class="{ 'px-2 pb-8 *:px-4': !isDesktop }"
        >
            <component :is="Modal.Header"
                ><component :is="Modal.Title">{{
                    t('moderation.report_title')
                }}</component
                ><component :is="Modal.Description">{{
                    t('moderation.report_warning')
                }}</component></component
            >
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="report-reason">{{
                        t('moderation.reason')
                    }}</Label
                    ><select
                        id="report-reason"
                        v-model="form.reason"
                        required
                        class="h-10 w-full rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>
                            {{ t('moderation.choose_reason') }}
                        </option>
                        <option
                            v-for="reason in reasons"
                            :key="reason"
                            :value="reason"
                        >
                            {{ t(`moderation.reasons.${reason}`) }}
                        </option></select
                    ><InputError :message="form.errors.reason" />
                </div>
                <div class="grid gap-2">
                    <Label for="report-details">{{
                        t('moderation.details')
                    }}</Label
                    ><Textarea
                        id="report-details"
                        v-model="form.details"
                        maxlength="1000"
                    /><InputError :message="form.errors.details" />
                </div>
                <fieldset v-if="canBlock" class="grid gap-2">
                    <legend>{{ t('moderation.block_question') }}</legend>
                    <label class="flex items-center gap-2"
                        ><input
                            id="report-block-yes"
                            v-model="form.block"
                            type="radio"
                            :value="true"
                        />{{ t('moderation.yes') }}</label
                    ><label class="flex items-center gap-2"
                        ><input
                            id="report-block-no"
                            v-model="form.block"
                            type="radio"
                            :value="false"
                        />{{ t('moderation.no') }}</label
                    >
                </fieldset>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('moderation.block_admin') }}
                </p>
                <InputError :message="form.errors.block" /><InputError
                    :message="form.errors.confirmed"
                /><InputError
                    :message="
                        (form.errors as Record<string, string>).conversation
                    "
                />
                <component :is="Modal.Footer"
                    ><component :is="Modal.Close" as-child
                        ><Button
                            type="button"
                            variant="outline"
                            data-test="cancel-report"
                            :disabled="form.processing"
                            >{{ t('common.actions.cancel') }}</Button
                        ></component
                    ><Button
                        type="submit"
                        data-test="confirm-report"
                        :disabled="form.processing"
                        >{{ t('moderation.report_confirm') }}</Button
                    ></component
                >
            </form>
        </component>
    </component>
</template>
