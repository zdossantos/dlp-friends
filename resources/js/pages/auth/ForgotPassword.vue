<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { localizeMailError } from '@/lib/mailError';
import { login } from '@/routes';
import { email } from '@/routes/password';

const { t } = useTranslations();

defineOptions({
    layout: {
        titleKey: 'account.forgot_password.title',
        descriptionKey: 'account.forgot_password.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('account.forgot_password.title')" />

    <div
        v-if="status"
        role="status"
        class="mb-4 rounded-xl bg-secondary p-3 text-center text-sm font-medium text-secondary-foreground"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ t('account.fields.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    :placeholder="t('account.fields.email_placeholder')"
                />
                <InputError :message="localizeMailError(errors.email, t)" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('account.forgot_password.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{
                t('account.forgot_password.back')
            }}</TextLink>
        </div>
    </div>
</template>
