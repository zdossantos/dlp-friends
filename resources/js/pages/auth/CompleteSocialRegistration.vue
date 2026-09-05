<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/social/registration';

const { t } = useTranslations();

defineOptions({
    layout: {
        titleKey: 'account.social_registration.title',
        descriptionKey: 'account.social_registration.description',
    },
});
</script>

<template>
    <Head :title="t('account.social_registration.title')" />

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="birth_date">{{
                t('account.social_registration.birth_date')
            }}</Label>
            <Input
                id="birth_date"
                type="date"
                required
                autofocus
                autocomplete="bday"
                name="birth_date"
            />
            <InputError :message="errors.birth_date" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            {{ t('account.social_registration.submit') }}
        </Button>
    </Form>
</template>
