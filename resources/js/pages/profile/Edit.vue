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
    age: number;
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
        class="mx-auto flex h-[calc(100svh-6rem-env(safe-area-inset-bottom))] w-full max-w-xl flex-col gap-3 overflow-hidden px-4 pt-[max(0.75rem,env(safe-area-inset-top))] sm:gap-5 sm:px-6 sm:pt-6"
    >
        <Heading
            class="shrink-0"
            title="Modifier mon profil"
            description="Mettez à jour les informations visibles par les autres membres."
        />
        <ProfileForm
            class="min-h-0 flex-1"
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
            :age="age"
        />
    </div>
</template>
