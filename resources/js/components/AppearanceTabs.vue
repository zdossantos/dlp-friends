<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

const tabs = [
    { value: 'light', Icon: Sun, label: t('account.appearance.light') },
    { value: 'dark', Icon: Moon, label: t('account.appearance.dark') },
    { value: 'system', Icon: Monitor, label: t('account.appearance.system') },
] as const;
</script>

<template>
    <div
        :aria-label="t('account.appearance.label')"
        class="inline-flex gap-1 rounded-xl bg-muted p-1"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-lg px-3 py-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                appearance === value
                    ? 'bg-card text-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-card/60 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
