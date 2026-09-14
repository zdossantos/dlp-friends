<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';

const passwordInput = useTemplateRef('passwordInput');
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('account.deletion.title')"
            :description="t('account.deletion.description')"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">{{ t('account.deletion.warning') }}</p>
                <p class="text-sm">
                    {{ t('account.deletion.irreversible') }}
                </p>
            </div>
            <component :is="Modal.Root">
                <component :is="Modal.Trigger" as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                        >{{ t('account.deletion.submit') }}</Button
                    >
                </component>
                <component
                    :is="Modal.Content"
                    :class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"
                >
                    <Form
                        v-bind="AccountController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <component :is="Modal.Header" class="space-y-3">
                            <component :is="Modal.Title">{{
                                t('account.deletion.question')
                            }}</component>
                            <component :is="Modal.Description">
                                {{ t('account.deletion.confirmation') }}
                            </component>
                        </component>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">{{
                                t('account.fields.password')
                            }}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                :placeholder="t('account.fields.password')"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <component :is="Modal.Footer" class="gap-2">
                            <component :is="Modal.Close" as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ t('common.actions.cancel') }}
                                </Button>
                            </component>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                                data-test="confirm-delete-user-button"
                            >
                                <Spinner v-if="processing" />
                                {{ t('account.deletion.submit') }}
                            </Button>
                        </component>
                    </Form>
                </component>
            </component>
        </div>
    </div>
</template>
