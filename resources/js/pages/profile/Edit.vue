<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import ProfileForm from '@/components/profile/ProfileForm.vue';
import { show, update } from '@/routes/member-profile';
import type { Profile } from '@/types';

defineProps<{
    profile: Profile;
    visitFrequencies: Array<{ value: string; label: string }>;
    visibilities: Array<{ value: string; label: string }>;
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
    <div class="mx-auto w-full max-w-2xl space-y-6 p-4 sm:p-6">
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
        />
    </div>
</template>
