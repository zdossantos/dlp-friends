<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    LayoutDashboard,
    LogOut,
    Pencil,
    Settings,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, logout } from '@/routes';
import { edit as editAccount } from '@/routes/account';
import { edit as editProfile, show } from '@/routes/member-profile';
import type { Profile, VisitFrequency } from '@/types';

const props = defineProps<{ profile: Profile; age: number }>();
const page = usePage();

const isAdmin = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'admin'),
);

const frequencyLabels: Record<VisitFrequency, string> = {
    rarely: 'Rarement',
    sometimes: 'De temps en temps',
    often: 'Souvent',
    very_often: 'Très souvent',
};

function handleLogout(): void {
    router.clearHistory();
    router.flushAll();
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Mon profil', href: show() }] },
});
</script>

<template>
    <Head :title="profile.display_name" />
    <main
        class="mx-auto w-full max-w-lg px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <section
            class="overflow-hidden rounded-[2rem] border border-border/70 bg-card shadow-xl shadow-primary/10"
        >
            <div
                v-if="profile.avatar"
                data-test="profile-avatar-hero"
                class="relative flex min-h-[23rem] items-end justify-center overflow-hidden px-8 pt-20 sm:min-h-[28rem]"
                :style="{
                    backgroundImage: `linear-gradient(145deg, ${profile.avatar.primary_color}, ${profile.avatar.secondary_color})`,
                }"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(255,255,255,.5),transparent_35%),radial-gradient(circle_at_15%_70%,rgba(255,255,255,.22),transparent_34%)]"
                />
                <div
                    class="absolute top-4 right-4 z-30 flex gap-2"
                    aria-label="Actions du profil"
                >
                    <Button
                        as-child
                        variant="secondary"
                        size="icon"
                        class="size-12 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                    >
                        <Link :href="editAccount()" aria-label="Réglages">
                            <Settings class="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                    <Button
                        v-if="isAdmin"
                        as-child
                        variant="secondary"
                        size="icon"
                        class="size-12 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                    >
                        <Link :href="dashboard()" aria-label="Administration">
                            <LayoutDashboard
                                class="size-5"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        variant="secondary"
                        size="icon"
                        class="size-12 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                    >
                        <Link
                            :href="logout()"
                            as="button"
                            aria-label="Se déconnecter"
                            @click="handleLogout"
                        >
                            <LogOut class="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
                <div
                    class="absolute right-[8%] bottom-[12%] size-32 rounded-full bg-white/20 blur-3xl"
                    aria-hidden="true"
                />
                <img
                    :src="profile.avatar.image_url"
                    :alt="`Avatar ${profile.avatar.name}`"
                    class="relative z-20 max-h-[22rem] w-full object-contain drop-shadow-2xl sm:max-h-[27rem]"
                    data-test="profile-avatar"
                />
            </div>

            <div
                data-test="profile-information-sheet"
                class="relative z-20 -mt-8 space-y-6 rounded-t-[2rem] bg-card p-6 sm:p-8"
            >
                <div
                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"
                >
                    <div>
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <h1 class="text-4xl font-semibold tracking-tight">
                                {{ profile.display_name }}
                            </h1>
                            <Badge
                                class="rounded-full px-3 py-1"
                                variant="secondary"
                                >{{ age }} ans</Badge
                            >
                        </div>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-2 font-medium"
                            >
                                <Eye
                                    v-if="profile.visibility === 'visible'"
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                <EyeOff
                                    v-else
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                {{
                                    profile.visibility === 'visible'
                                        ? 'Visible'
                                        : 'Masqué'
                                }}
                            </span>
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-secondary px-3 py-2 font-medium text-secondary-foreground"
                            >
                                <Sparkles class="size-4" aria-hidden="true" />
                                {{
                                    frequencyLabels[
                                        props.profile.visit_frequency!
                                    ]
                                }}
                            </span>
                        </div>
                    </div>
                    <Button
                        as-child
                        variant="outline"
                        class="min-h-12 rounded-full"
                    >
                        <Link :href="editProfile()">
                            <Pencil class="size-4" aria-hidden="true" />
                            Modifier mon profil
                        </Link>
                    </Button>
                </div>

                <div>
                    <h2 class="mb-2 text-sm font-medium">À propos</h2>
                    <p
                        class="leading-7 whitespace-pre-line text-muted-foreground"
                    >
                        {{
                            profile.bio ||
                            'Aucune bio renseignée pour le moment.'
                        }}
                    </p>
                </div>
                <div v-if="profile.interests?.length">
                    <h2 class="mb-3 text-sm font-medium">Intérêts</h2>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="interest in profile.interests"
                            :key="interest.id"
                            variant="secondary"
                            class="rounded-full px-3 py-1.5"
                        >
                            {{ interest.name }}
                        </Badge>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
