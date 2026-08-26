<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6 md:px-10"
    >
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_48%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_42%)] opacity-45"
        />
        <div class="absolute top-4 right-4 z-10 flex items-center gap-3 sm:top-6 sm:right-6">
            <LocaleSwitcher />
            <AppearanceTabs />
        </div>
        <div class="relative flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-3 self-center rounded-lg font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20"
                >
                    <AppLogoIcon class="size-7 fill-current" />
                </div>
                <span class="text-lg font-semibold tracking-tight"
                    >DLP Friends</span
                >
            </Link>

            <main id="contenu-principal" class="flex flex-col gap-6">
                <Card
                    class="rounded-3xl border-border/70 shadow-xl shadow-primary/5"
                >
                    <CardHeader class="px-6 pt-8 pb-0 text-center sm:px-10">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-6 py-8 sm:px-10">
                        <slot />
                    </CardContent>
                </Card>
            </main>
        </div>
    </div>
</template>
