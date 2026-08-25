<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProfileForm from '@/components/profile/ProfileForm.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as avatarIndex } from '@/routes/admin/avatars';
import { store } from '@/routes/member-profile';
import type { AvatarOption, InterestOption, Profile } from '@/types';

defineProps<{
    profile: Profile | null;
    canManageAvatars: boolean;
    visitFrequencies: Array<{ value: string; label: string }>;
    visibilities: Array<{ value: string; label: string }>;
    avatars: AvatarOption[];
    interests: InterestOption[];
    selectedInterestIds: number[];
    interestLimit: number;
}>();
</script>

<template>
    <Head title="Créer mon profil" />

    <main
        class="mx-auto flex min-h-svh w-full max-w-xl px-4 pt-[max(1.25rem,env(safe-area-inset-top))] pb-8 sm:px-6 sm:pt-8"
    >
        <Card
            class="h-fit w-full rounded-3xl border-border/70 shadow-xl shadow-primary/5"
        >
            <CardHeader>
                <CardTitle>Créons votre profil</CardTitle>
                <CardDescription>
                    Ces informations aideront les autres membres à vous
                    découvrir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="avatars.length === 0 && canManageAvatars"
                    class="mb-6 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
                >
                    Le catalogue est vide.
                    <Link :href="avatarIndex()" class="font-medium underline">
                        Ajouter le premier avatar
                    </Link>
                    pour permettre la complétion des profils.
                </p>
                <ProfileForm
                    :profile="profile"
                    :action="store.url()"
                    method="post"
                    submit-label="Créer mon profil"
                    :visit-frequencies="visitFrequencies"
                    :visibilities="visibilities"
                    :avatars="avatars"
                    :interests="interests"
                    :selected-interest-ids="selectedInterestIds"
                    :interest-limit="interestLimit"
                />
            </CardContent>
        </Card>
    </main>
</template>
