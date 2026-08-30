<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground shadow-sm"
    >
        <AppLogoIcon class="size-6 fill-current" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-accent leading-tight font-bold">
            {{ t('common.brand.name') }}
        </span>
    </div>
</template>
