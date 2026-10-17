<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import SocialLoginButtons from '@/components/auth/SocialLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
    emailRegistrationAvailable: boolean;
}>();
const { t } = useTranslations();
const legal = usePage().props.legal;

defineOptions({
    layout: {
        titleKey: 'account.registration.title',
        descriptionKey: 'account.registration.description',
    },
});
</script>

<template>
    <Head :title="t('account.registration.title')" />

    <SocialLoginButtons />

    <p
        v-if="!emailRegistrationAvailable"
        role="status"
        class="text-sm text-muted-foreground"
        data-test="email-registration-unavailable"
    >
        {{ t('account.registration.email_unavailable') }}
    </p>

    <Form
        v-if="emailRegistrationAvailable"
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('account.registration.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    :placeholder="t('account.registration.email_placeholder')"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="birth_date">{{
                    t('account.registration.birth_date')
                }}</Label>
                <Input
                    id="birth_date"
                    type="date"
                    required
                    autocomplete="bday"
                    name="birth_date"
                />
                <InputError :message="errors.birth_date" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    t('account.registration.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    :placeholder="t('account.registration.password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    t('account.registration.password_confirmation')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="
                        t('account.registration.password_confirmation')
                    "
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-start gap-3">
                    <Checkbox
                        id="terms_accepted"
                        name="terms_accepted"
                        value="1"
                    />
                    <Label
                        for="terms_accepted"
                        class="text-sm leading-5 font-normal"
                    >
                        {{ t('account.registration.terms_acceptance') }}
                        <a :href="legal.terms_url" class="underline">{{
                            t('common.legal.terms')
                        }}</a>
                        ·
                        <a :href="legal.privacy_url" class="underline">{{
                            t('common.legal.privacy')
                        }}</a>
                    </Label>
                </div>
                <InputError :message="errors.terms_accepted" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                :aria-busy="processing ? 'true' : undefined"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ t('account.registration.submit') }}
            </Button>
        </div>
    </Form>

    <div class="text-center text-sm text-muted-foreground">
        {{ t('account.registration.existing_account') }}
        <TextLink :href="login()" class="underline underline-offset-4">{{
            t('account.registration.login')
        }}</TextLink>
    </div>
</template>
