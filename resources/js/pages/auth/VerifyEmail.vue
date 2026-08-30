<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { localizeMailError } from '@/lib/mailError';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const { t } = useTranslations();

defineOptions({
    layout: {
        titleKey: 'account.verification.title',
        descriptionKey: 'account.verification.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('account.verification.page_title')" />

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="mb-4 rounded-xl bg-secondary p-3 text-center text-sm font-medium text-secondary-foreground"
    >
        {{ t('account.verification.sent') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ errors, processing }"
    >
        <InputError :message="localizeMailError(errors.email, t)" />

        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ t('account.verification.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ t('account.verification.logout') }}
        </TextLink>
    </Form>
</template>
