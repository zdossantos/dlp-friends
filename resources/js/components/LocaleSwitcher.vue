<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/locale';
import type { Locale } from '@/types/i18n';

const { locale, t } = useTranslations();
const status = ref('');

function changeLocale(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as Locale;

    router.patch(
        update(),
        { locale: value },
        {
            preserveScroll: true,
            onSuccess: () => {
                status.value = t('locale.label');
            },
        },
    );
}
</script>

<template>
    <label
        data-test="locale-switcher"
        class="inline-flex items-center gap-2 text-sm font-medium"
    >
        <span>{{ t('locale.label') }}</span>
        <select
            name="locale"
            :value="locale"
            class="rounded-md border bg-background px-2 py-1 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @change="changeLocale"
        >
            <option value="fr">{{ t('locale.fr') }}</option>
            <option value="en">{{ t('locale.en') }}</option>
        </select>
        <span class="sr-only" aria-live="polite">{{ status }}</span>
    </label>
</template>
