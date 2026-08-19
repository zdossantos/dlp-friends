<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProfileForm from '@/components/profile/ProfileForm.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/member-profile';
import type { Profile } from '@/types';

defineProps<{
    profile: Profile | null;
    visitFrequencies: Array<{ value: string; label: string }>;
    visibilities: Array<{ value: string; label: string }>;
}>();
</script>

<template>
    <Head title="Créer mon profil" />

    <main
        class="mx-auto flex min-h-[calc(100vh-5rem)] w-full max-w-2xl items-center px-4 py-10"
    >
        <Card class="w-full">
            <CardHeader>
                <CardTitle>Créons votre profil</CardTitle>
                <CardDescription>
                    Ces informations aideront les autres membres à vous
                    découvrir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ProfileForm
                    :profile="profile"
                    :action="store.url()"
                    method="post"
                    submit-label="Créer mon profil"
                    :visit-frequencies="visitFrequencies"
                    :visibilities="visibilities"
                />
            </CardContent>
        </Card>
    </main>
</template>
