<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import ProfileForm from '@/components/profile/ProfileForm.vue';
import { show, update } from '@/routes/member-profile';
import type { AvatarOption, InterestOption, Profile } from '@/types';

defineProps<{
    profile: Profile;
    visitFrequencies: Array<{ value: string; label: string }>;
    visibilities: Array<{ value: string; label: string }>;
    avatars: AvatarOption[];
    interests: InterestOption[];
    selectedInterestIds: number[];
    interestLimit: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mon profil', href: show() },
            { title: 'Modifier' },
        ],
    },
});
</script>

<template>
    <Head title="Modifier mon profil" />
    <div
        class="mx-auto w-full max-w-xl space-y-6 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <Heading
            title="Modifier mon profil"
            description="Mettez à jour les informations visibles par les autres membres."
        />
        <ProfileForm
            :profile="profile"
            :action="update.url()"
            method="patch"
            submit-label="Enregistrer"
            :visit-frequencies="visitFrequencies"
            :visibilities="visibilities"
            :avatars="avatars"
            :interests="interests"
            :selected-interest-ids="selectedInterestIds"
            :interest-limit="interestLimit"
        />
    </div>
</template>
