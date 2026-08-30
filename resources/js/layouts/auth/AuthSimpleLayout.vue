<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
const { t } = useTranslations();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-4 sm:p-6 md:p-10"
    >
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_48%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_42%)] opacity-45"
        />
        <div class="absolute top-4 right-4 z-10 sm:top-6 sm:right-6">
            <LocaleSwitcher />
        </div>
        <div class="relative w-full max-w-md">
            <main
                id="contenu-principal"
                class="flex flex-col gap-8 rounded-3xl border border-border/70 bg-card/95 p-6 shadow-xl shadow-primary/5 backdrop-blur sm:p-10"
            >
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 rounded-xl font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <div
                            class="mb-1 flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20"
                        >
                            <AppLogoIcon class="size-8 fill-current" />
                        </div>
                        <span
                            class="font-accent text-lg font-bold tracking-tight"
                            >{{ t('common.brand.name') }}</span
                        >
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
