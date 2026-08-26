<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid min-h-svh flex-col items-center justify-center bg-background px-6 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col overflow-hidden bg-primary p-10 text-primary-foreground lg:flex"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_45%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_40%)] opacity-30"
            />
            <Link
                :href="home()"
                class="relative z-20 flex items-center text-lg font-medium"
            >
                <AppLogoIcon class="mr-2 size-8 fill-current text-white" />
                DLP Friends
            </Link>
            <p class="relative z-20 mt-auto max-w-md text-2xl font-medium">
                Les rencontres Disney commencent par un profil qui vous
                ressemble.
            </p>
        </div>
        <div class="relative p-6 lg:p-8">
            <div class="absolute top-4 right-4"><LocaleSwitcher /></div>
            <main
                id="contenu-principal"
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
