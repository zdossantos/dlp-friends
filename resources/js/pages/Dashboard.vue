<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

defineProps<{
    stats: {
        totalAccounts: number;
        activeAccounts: number;
        verifiedAccounts: number;
        completedProfiles: number;
    };
    recentRegistrations: Array<{
        email: string;
        status: string;
        profile_completed: boolean;
        registered_at: string | null;
    }>;
}>();

const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Date inconnue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Administration',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Administration" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div>
            <p class="text-sm font-medium text-primary">Vue d’ensemble</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Administration
            </h1>
            <p class="mt-1 text-muted-foreground">
                Suivez l’activité et la complétion des comptes membres.
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Comptes créés</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.totalAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Comptes actifs</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.activeAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Emails vérifiés</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.verifiedAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card class="border-accent bg-accent/25">
                <CardHeader class="pb-2">
                    <CardDescription>Profils complétés</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.completedProfiles
                    }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>Inscriptions récentes</CardTitle>
                <CardDescription>
                    Les huit derniers comptes créés sur la plateforme.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="recentRegistrations.length" class="divide-y">
                    <div
                        v-for="registration in recentRegistrations"
                        :key="`${registration.email}-${registration.registered_at}`"
                        class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="font-medium">{{ registration.email }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(registration.registered_at) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {{
                                    registration.status === 'active'
                                        ? 'Actif'
                                        : 'Inactif'
                                }}
                            </Badge>
                            <Badge
                                :variant="
                                    registration.profile_completed
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{
                                    registration.profile_completed
                                        ? 'Profil complété'
                                        : 'Profil à compléter'
                                }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="py-8 text-center text-muted-foreground">
                    Aucune inscription pour le moment.
                </p>
            </CardContent>
        </Card>
    </main>
</template>
