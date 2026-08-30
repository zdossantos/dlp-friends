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
import { useTranslations } from '@/composables/useTranslations';

const { t, formatDate: localizeDate } = useTranslations();

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
        ? localizeDate(value, { dateStyle: 'medium', timeStyle: 'short' })
        : t('administration.dashboard.unknown_date');
</script>

<template>
    <Head :title="t('administration.title')" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <div>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.dashboard.eyebrow') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ t('administration.title') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ t('administration.dashboard.description') }}
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>{{
                        t('administration.dashboard.total_accounts')
                    }}</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.totalAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>{{
                        t('administration.dashboard.active_accounts')
                    }}</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.activeAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>{{
                        t('administration.dashboard.verified_accounts')
                    }}</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.verifiedAccounts
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card class="border-accent bg-accent/25">
                <CardHeader class="pb-2">
                    <CardDescription>{{
                        t('administration.dashboard.completed_profiles')
                    }}</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats.completedProfiles
                    }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('administration.dashboard.recent_title')
                }}</CardTitle>
                <CardDescription>
                    {{ t('administration.dashboard.recent_description') }}
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
                                        ? t('administration.common.active')
                                        : t('administration.common.inactive')
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
                                        ? t(
                                              'administration.dashboard.profile_completed',
                                          )
                                        : t(
                                              'administration.dashboard.profile_incomplete',
                                          )
                                }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="py-8 text-center text-muted-foreground">
                    {{ t('administration.dashboard.empty') }}
                </p>
            </CardContent>
        </Card>
    </main>
</template>
