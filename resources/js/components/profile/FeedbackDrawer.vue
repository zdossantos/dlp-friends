<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ChevronRight, Lightbulb, X } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/feedback';
import type { FeedbackOptions } from '@/types/feedback';

defineProps<{ options: FeedbackOptions }>();
const { t } = useTranslations();
const form = useForm({ kind: 'idea', area: '', content: '' });
const trigger = useTemplateRef<HTMLButtonElement>('trigger');
const sent = ref(false);
function restoreFocus(event: Event): void {
    event.preventDefault();
    trigger.value?.focus({ preventScroll: true });
}
const failure = ref('');
function submit(): void {
    failure.value = '';
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            sent.value = true;
        },
        onHttpException: (event) => {
            failure.value = t(
                event.status === 429 ? 'feedback.throttled' : 'feedback.failed',
            );

            return false;
        },
        onNetworkError: () => {
            failure.value = t('feedback.failed');

            return false;
        },
    });
}
</script>

<template>
    <Drawer
        @update:open="
            (open) => {
                if (open) sent = false;
            }
        "
    >
        <DrawerTrigger as-child>
            <button
                type="button"
                ref="trigger"
                data-test="feedback-trigger"
                class="flex min-h-11 w-full items-center gap-3 rounded-xl bg-primary/10 px-4 py-3 text-left text-sm font-medium text-primary transition-colors hover:bg-primary/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <Lightbulb class="size-5 shrink-0" aria-hidden="true" />
                <span class="flex-1">{{ t('feedback.trigger') }}</span>
                <ChevronRight class="size-4" aria-hidden="true" />
            </button>
        </DrawerTrigger>
        <DrawerContent
            data-test="feedback-drawer"
            @close-auto-focus="restoreFocus"
            class="mx-auto w-full max-w-lg overflow-y-auto data-[swipe-direction=down]:max-h-[92svh]"
        >
            <div
                class="grid gap-4 px-5 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
            >
                <DrawerTitle class="sr-only">{{
                    t('feedback.trigger')
                }}</DrawerTitle>
                <DrawerClose as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="justify-self-end"
                        data-test="feedback-close"
                        :aria-label="t('feedback.close')"
                        ><X class="size-5" aria-hidden="true"
                    /></Button>
                </DrawerClose>
                <DrawerDescription class="text-sm text-muted-foreground">{{
                    t('feedback.help')
                }}</DrawerDescription>
                <p
                    v-if="sent"
                    role="status"
                    data-test="feedback-success"
                    class="py-6 text-center font-medium"
                >
                    {{ t('feedback.success') }}
                </p>
                <form v-else class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2 *:w-full">
                        <Label for="feedback-kind">{{
                            t('feedback.kind')
                        }}</Label>
                        <NativeSelect
                            id="feedback-kind"
                            v-model="form.kind"
                            required
                            class="min-h-11"
                            :aria-invalid="!!form.errors.kind"
                            aria-describedby="feedback-kind-error"
                        >
                            <NativeSelectOption
                                v-for="kind in options.kinds"
                                :key="kind"
                                :value="kind"
                                >{{
                                    t(`feedback.kinds.${kind}`)
                                }}</NativeSelectOption
                            >
                        </NativeSelect>
                        <InputError
                            id="feedback-kind-error"
                            :message="form.errors.kind"
                        />
                    </div>
                    <div class="grid gap-2 *:w-full">
                        <Label for="feedback-area">{{
                            t('feedback.area')
                        }}</Label>
                        <NativeSelect
                            id="feedback-area"
                            v-model="form.area"
                            required
                            class="min-h-11"
                            :aria-invalid="!!form.errors.area"
                            aria-describedby="feedback-area-error"
                        >
                            <NativeSelectOption value="" disabled>{{
                                t('feedback.choose')
                            }}</NativeSelectOption>
                            <NativeSelectOption
                                v-for="area in options.areas"
                                :key="area"
                                :value="area"
                                >{{
                                    t(`feedback.areas.${area}`)
                                }}</NativeSelectOption
                            >
                        </NativeSelect>
                        <InputError
                            id="feedback-area-error"
                            :message="form.errors.area"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="feedback-content">{{
                            t('feedback.content')
                        }}</Label>
                        <Textarea
                            id="feedback-content"
                            v-model="form.content"
                            required
                            maxlength="5000"
                            rows="5"
                            :aria-invalid="!!form.errors.content"
                            aria-describedby="feedback-content-error"
                        />
                        <InputError
                            id="feedback-content-error"
                            :message="form.errors.content"
                        />
                    </div>
                    <p
                        v-if="failure"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ failure }}
                    </p>
                    <Button
                        type="submit"
                        data-test="feedback-submit"
                        class="min-h-11"
                        :disabled="form.processing"
                        >{{ t('feedback.send') }}</Button
                    >
                </form>
            </div>
        </DrawerContent>
    </Drawer>
</template>
