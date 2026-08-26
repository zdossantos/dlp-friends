<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
        class="mx-auto flex h-full min-h-0 w-full max-w-xl flex-col overflow-hidden px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.5rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-6"
    >
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
