<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, show } from '@/routes/member-profile';
import type { Profile, VisitFrequency } from '@/types';

const props = defineProps<{ profile: Profile; age: number }>();

const frequencyLabels: Record<VisitFrequency, string> = {
    rarely: 'Rarement',
    sometimes: 'De temps en temps',
    often: 'Souvent',
    very_often: 'Très souvent',
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Mon profil', href: show() }] },
});
</script>

<template>
    <Head :title="profile.display_name" />
    <main class="mx-auto w-full max-w-3xl p-4 sm:p-6">
        <section
            class="space-y-6 rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
        >
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
                    <Link :href="edit()"
                        ><Pencil class="size-4" /> Modifier mon profil</Link
                    >
                </Button>
            </div>
            <div>
                <h2 class="mb-2 text-sm font-medium">À propos</h2>
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ profile.bio || 'Aucune bio renseignée pour le moment.' }}
                </p>
            </div>
        </section>
    </main>
</template>
