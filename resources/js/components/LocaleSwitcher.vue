<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/locale';
import type { Locale } from '@/types/i18n';

const { locale, t } = useTranslations();
const status = ref('');
const isChanging = ref(false);

function changeLocale(value: Locale): void {
    if (value === locale.value || isChanging.value) {
        return;
    }

    isChanging.value = true;

    router.patch(
        update(),
        { locale: value },
        {
            preserveScroll: true,
            onSuccess: () => {
                status.value = t('locale.label');
                window.location.reload();
            },
            onFinish: () => {
                isChanging.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        data-test="locale-switcher"
        :aria-label="t('locale.label')"
        class="inline-flex max-w-full items-center gap-1 rounded-full border border-border/70 bg-card/95 p-1 shadow-sm backdrop-blur"
    >
        <Languages
            class="ml-2 size-4 shrink-0 text-muted-foreground"
            aria-hidden="true"
        />
        <button
            v-for="value in ['fr', 'en'] as const"
            :key="value"
            type="button"
            :data-test="`locale-${value}`"
            :aria-pressed="locale === value"
            :disabled="isChanging"
            :class="[
                'inline-flex min-h-10 min-w-10 items-center justify-center rounded-full px-3 text-xs font-semibold tracking-wide uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60',
                locale === value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            ]"
            @click="changeLocale(value)"
        >
            {{ value }}
        </button>
        <span class="sr-only" aria-live="polite">{{ status }}</span>
    </div>
</template>
