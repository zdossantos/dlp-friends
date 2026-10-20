<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/appearance';

const { t } = useTranslations();
setLayoutProps({
    breadcrumbs: [{ title: t('account.settings.appearance'), href: edit() }],
});
</script>

<template>
    <Head :title="t('account.settings.appearance')" />

    <SettingsSection
        :title="t('account.settings.appearance')"
        :description="t('account.settings.appearance_description')"
    >
        <AppearanceTabs />
    </SettingsSection>
</template>
