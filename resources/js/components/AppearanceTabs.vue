<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

const tabs = [
    { value: 'light', Icon: Sun, label: t('account.appearance.light') },
    { value: 'dark', Icon: Moon, label: t('account.appearance.dark') },
    { value: 'system', Icon: Monitor, label: t('account.appearance.system') },
] as const;

function selectAppearance(value: string | number): void {
    updateAppearance(value as Appearance);
}
</script>

<template>
    <Tabs
        :model-value="appearance"
        :aria-label="t('account.appearance.label')"
        @update:model-value="selectAppearance"
    >
        <TabsList class="h-auto rounded-xl p-1">
            <TabsTrigger
                v-for="{ value, Icon, label } in tabs"
                :key="value"
                :value="value"
                class="rounded-lg px-3 py-1.5"
            >
                <component :is="Icon" class="-ml-1 size-4" />
                <span class="ml-1.5 text-sm">{{ label }}</span>
            </TabsTrigger>
        </TabsList>
    </Tabs>
</template>
