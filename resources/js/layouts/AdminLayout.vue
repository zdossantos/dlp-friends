<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: (BreadcrumbItem & { titleKey?: TranslationKey })[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
const { t } = useTranslations();
const translatedBreadcrumbs = computed(() =>
    props.breadcrumbs.map(({ titleKey, ...breadcrumb }) => ({
        ...breadcrumb,
        title: titleKey ? t(titleKey) : breadcrumb.title,
    })),
);
</script>

<template>
    <AppSidebarLayout :breadcrumbs="translatedBreadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
