<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import BackLink from '@/components/common/BackLink.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { index, update } from '@/routes/admin/feedback';
import { index as members } from '@/routes/admin/members';
import type { FeedbackDetail, FeedbackOptions } from '@/types/feedback';
const props = defineProps<{
    feedback: FeedbackDetail;
    options: FeedbackOptions;
}>();
const { t, formatDate } = useTranslations();
const form = useForm({
    status: props.feedback.status,
    internal_note: props.feedback.internal_note ?? '',
});
</script>

<template>
    <Head :title="t('feedback.title')" />
    <main
        class="mx-auto flex w-full max-w-3xl min-w-0 flex-col gap-6 p-4 sm:p-6"
    >
        <BackLink :href="index()">{{ t('feedback.back') }}</BackLink>
        <AdminPageHeader
            :title="t(`feedback.kinds.${feedback.kind}`)"
            :description="t(`feedback.areas.${feedback.area}`)"
        />
        <dl class="grid gap-2 text-sm">
            <div>
                <dt class="inline font-medium">{{ t('feedback.author') }}:</dt>
                <dd class="inline break-words">
                    {{ feedback.author.name }} ·
                    <Link
                        :href="
                            members({ query: { member: feedback.author.id } })
                        "
                        class="text-primary underline"
                        >{{ t('feedback.member_page') }}</Link
                    >
                </dd>
            </div>
            <div>
                <dt class="inline font-medium">{{ t('feedback.date') }}:</dt>
                <dd class="inline">{{ formatDate(feedback.created_at) }}</dd>
            </div>
        </dl>
        <p class="rounded-xl border p-4 break-words whitespace-pre-wrap">
            {{ feedback.content }}
        </p>
        <form
            class="grid gap-4"
            @submit.prevent="
                form.patch(update.url(feedback.id), { preserveScroll: true })
            "
        >
            <p class="text-sm text-muted-foreground">
                {{ t('feedback.private') }}
            </p>
            <div class="grid gap-2 *:w-full">
                <Label for="feedback-status">{{ t('feedback.status') }}</Label>
                <NativeSelect
                    id="feedback-status"
                    v-model="form.status"
                    required
                    class="min-h-11"
                    :aria-invalid="!!form.errors.status"
                    aria-describedby="feedback-status-error"
                >
                    <NativeSelectOption
                        v-for="status in options.statuses"
                        :key="status"
                        :value="status"
                        >{{
                            t(`feedback.statuses.${status}`)
                        }}</NativeSelectOption
                    >
                </NativeSelect>
                <InputError
                    id="feedback-status-error"
                    :message="form.errors.status"
                />
            </div>
            <div class="grid gap-2">
                <Label for="feedback-note">{{
                    t('feedback.internal_note')
                }}</Label>
                <Textarea
                    id="feedback-note"
                    v-model="form.internal_note"
                    maxlength="5000"
                    rows="6"
                    :aria-invalid="!!form.errors.internal_note"
                    aria-describedby="feedback-note-error"
                />
                <InputError
                    id="feedback-note-error"
                    :message="form.errors.internal_note"
                />
            </div>
            <Button
                type="submit"
                data-test="feedback-save"
                :disabled="form.processing"
                >{{ t('feedback.save') }}</Button
            >
            <p
                v-if="form.recentlySuccessful"
                role="status"
                data-test="feedback-saved"
            >
                {{ t('feedback.saved') }}
            </p>
        </form>
    </main>
</template>
