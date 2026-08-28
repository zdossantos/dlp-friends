<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import AuthLayout from '@/layouts/auth/AuthCardLayout.vue';

const {
    title = '',
    description = '',
    titleKey,
    descriptionKey,
} = defineProps<{
    title?: string;
    description?: string;
    titleKey?: TranslationKey;
    descriptionKey?: TranslationKey;
}>();
const { t } = useTranslations();
</script>

<template>
    <AuthLayout
        :title="titleKey ? t(titleKey) : title"
        :description="descriptionKey ? t(descriptionKey) : description"
    >
        <slot />
    </AuthLayout>
</template>
