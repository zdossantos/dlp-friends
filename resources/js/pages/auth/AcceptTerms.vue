<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { store } from '@/routes/terms/acceptance';

defineProps<{ version: string }>();
const { t } = useTranslations();
const legal = usePage().props.legal;
const form = useForm({ accepted: false });
defineOptions({
    layout: {
        titleKey: 'moderation.terms_title',
        descriptionKey: 'moderation.terms_description',
    },
});
</script>
<template>
    <Head :title="t('moderation.terms_title')" />
    <form class="grid gap-6" @submit.prevent="form.post(store().url)">
        <p>{{ t('moderation.terms_changes') }}</p>
        <p class="text-sm text-muted-foreground">{{ version }}</p>
        <a
            :href="legal.terms_url"
            target="_blank"
            rel="noopener"
            class="underline"
            >{{ t('moderation.read_terms') }}</a
        >
        <a
            :href="legal.privacy_url"
            target="_blank"
            rel="noopener"
            class="underline"
            >{{ t('moderation.read_privacy') }}</a
        >
        <div class="flex items-start gap-3">
            <Checkbox id="accept-current-terms" v-model="form.accepted" /><Label
                for="accept-current-terms"
                >{{ t('moderation.terms_accept') }}</Label
            >
        </div>
        <InputError :message="form.errors.accepted" />
        <Button type="submit" :disabled="!form.accepted || form.processing">{{
            t('moderation.terms_continue')
        }}</Button>
        <Link :href="logout()" method="post" as="button">{{
            t('account.verification.logout')
        }}</Link>
    </form>
</template>
