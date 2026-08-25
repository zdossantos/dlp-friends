<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, LogOut, Pencil, Settings } from '@lucide/vue';
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
        class="mx-auto w-full max-w-md px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <section
            class="space-y-6 rounded-3xl border border-border/70 bg-card/95 p-6 shadow-xl shadow-primary/5 backdrop-blur sm:p-8"
        >
            <div class="flex justify-end gap-2" aria-label="Actions du profil">
                <Button as-child variant="outline" size="icon" class="size-12">
                    <Link :href="editAccount()" aria-label="Réglages">
                        <Settings class="size-5" aria-hidden="true" />
                    </Link>
                </Button>
                <Button
                    v-if="isAdmin"
                    as-child
                    variant="outline"
                    size="icon"
                    class="size-12"
                >
                    <Link :href="dashboard()" aria-label="Administration">
                        <LayoutDashboard class="size-5" aria-hidden="true" />
                    </Link>
                </Button>
                <Button as-child variant="outline" size="icon" class="size-12">
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
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"
            >
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-3xl font-semibold tracking-tight">
                            {{ profile.display_name }}
                        </h1>
                        <Badge variant="secondary">{{ age }} ans</Badge>
                        <Badge>{{
                            profile.visibility === 'visible'
                                ? 'Visible'
                                : 'Masqué'
                        }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Visites :
                        {{ frequencyLabels[props.profile.visit_frequency!] }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="editProfile()"
                        ><Pencil class="size-4" aria-hidden="true" /> Modifier
                        mon profil</Link
                    >
                </Button>
            </div>
            <div>
                <h2 class="mb-2 text-sm font-medium">À propos</h2>
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ profile.bio || 'Aucune bio renseignée pour le moment.' }}
                </p>
            </div>
            <div v-if="profile.interests?.length">
                <h2 class="mb-2 text-sm font-medium">Intérêts</h2>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="interest in profile.interests"
                        :key="interest.id"
                        variant="secondary"
                    >
                        {{ interest.name }}
                    </Badge>
                </div>
            </div>
        </section>
    </main>
</template>
