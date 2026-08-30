<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        titleKey: 'account.confirm_password.title',
        descriptionKey: 'account.confirm_password.description',
    },
});
const { t } = useTranslations();
</script>

<template>
    <Head :title="t('account.confirm_password.page_title')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="t('account.confirm_password.passkey')"
        :loading-label="t('account.confirm_password.loading')"
        :separator="t('account.confirm_password.separator')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">{{ t('account.fields.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('account.confirm_password.submit') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
