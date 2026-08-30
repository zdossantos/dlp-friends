<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MessageCircle, Sparkles, UsersRound } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { app, login, register } from '@/routes';

const { t } = useTranslations();

const benefits = [
    {
        title: 'common.welcome.benefits.interests.title' as const,
        description: 'common.welcome.benefits.interests.description' as const,
        icon: Sparkles,
    },
    {
        title: 'common.welcome.benefits.discovery.title' as const,
        description: 'common.welcome.benefits.discovery.description' as const,
        icon: UsersRound,
    },
    {
        title: 'common.welcome.benefits.conversations.title' as const,
        description:
            'common.welcome.benefits.conversations.description' as const,
        icon: MessageCircle,
    },
];
</script>

<template>
    <Head :title="t('common.brand.name')" />

    <div
        class="relative min-h-svh overflow-hidden bg-background text-foreground"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_44%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_40%)] opacity-55"
        />

        <div
            class="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8"
        >
            <header
                class="flex flex-col items-stretch gap-3 py-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-11 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20"
                    >
                        <AppLogoIcon
                            class="size-7 fill-current"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="font-accent text-lg font-bold tracking-tight">
                        {{ t('common.brand.name') }}
                    </span>
                </div>
                <div class="flex items-center gap-3 self-end sm:self-auto">
                    <LocaleSwitcher />
                </div>
            </header>

            <main
                id="contenu-principal"
                class="flex flex-1 flex-col justify-center py-12 sm:py-16 lg:py-20"
            >
                <section class="mx-auto w-full max-w-3xl text-center">
                    <p
                        class="mx-auto mb-5 w-fit rounded-full bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground"
                    >
                        {{ t('common.welcome.eyebrow') }}
                    </p>
                    <h1
                        class="font-accent text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {{ t('common.welcome.title') }}
                    </h1>
                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                    >
                        {{ t('common.welcome.description') }}
                    </p>

                    <div
                        class="mt-8 flex flex-col justify-center gap-3 sm:flex-row"
                    >
                        <Link
                            v-if="$page.props.auth.user"
                            :href="app()"
                            class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-primary px-6 font-medium text-primary-foreground shadow-lg shadow-primary/20 transition-transform hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {{ t('common.welcome.open_space') }}
                        </Link>
                        <template v-else>
                            <Link
                                :href="register()"
                                class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-primary px-6 font-medium text-primary-foreground shadow-lg shadow-primary/20 transition-transform hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                {{ t('common.welcome.create_account') }}
                            </Link>
                            <Link
                                :href="login()"
                                class="inline-flex min-h-12 items-center justify-center rounded-2xl border bg-card px-6 font-medium shadow-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                {{ t('common.welcome.login') }}
                            </Link>
                        </template>
                    </div>
                </section>

                <section
                    class="mx-auto mt-14 grid w-full max-w-5xl gap-4 sm:grid-cols-3"
                    :aria-label="t('common.welcome.how_it_works')"
                >
                    <article
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="rounded-3xl border border-border/70 bg-card/90 p-6 shadow-lg shadow-primary/5 backdrop-blur"
                    >
                        <span
                            class="mb-4 grid size-11 place-items-center rounded-2xl bg-secondary text-primary"
                        >
                            <component
                                :is="benefit.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <h2 class="font-semibold">{{ t(benefit.title) }}</h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t(benefit.description) }}
                        </p>
                    </article>
                </section>

                <p
                    class="mx-auto mt-10 max-w-2xl text-center text-xs leading-5 text-muted-foreground"
                >
                    {{ t('common.brand.disclaimer') }}
                </p>
            </main>
        </div>
    </div>
</template>
