<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{ homeUrl: string; inertiaHome: boolean }>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('common.errors.not_found_title')">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        id="contenu-principal"
        class="flex min-h-svh items-center justify-center bg-background px-4 py-12 text-foreground"
    >
        <section class="w-full max-w-xl text-center">
            <AppLogoIcon class="mx-auto size-16" aria-hidden="true" />
            <p class="mt-8 font-accent text-6xl font-bold text-primary">404</p>
            <h1 class="mt-4 font-accent text-3xl font-bold sm:text-4xl">
                {{ t('common.errors.not_found_title') }}
            </h1>
            <p class="mt-4 text-lg leading-8 text-muted-foreground">
                {{ t('common.errors.not_found_description') }}
            </p>
            <component
                :is="inertiaHome ? Link : 'a'"
                :href="homeUrl"
                data-test="not-found-home"
                class="mt-8 inline-flex min-h-12 items-center justify-center rounded-2xl bg-primary px-6 font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none"
            >
                {{ t('common.errors.not_found_action') }}
            </component>
        </section>
    </main>
</template>
