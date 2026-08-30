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
import { useTranslations } from '@/composables/useTranslations';
import { index as avatarIndex } from '@/routes/admin/avatars';
import { store } from '@/routes/member-profile';
import type { AvatarOption, InterestOption, Profile } from '@/types';

const { t } = useTranslations();

defineProps<{
    profile: Profile | null;
    canManageAvatars: boolean;
    visitFrequencies: Array<{ value: string; label: string }>;
    visibilities: Array<{ value: string; label: string }>;
    avatars: AvatarOption[];
    interests: InterestOption[];
    selectedInterestIds: number[];
    interestLimit: number;
    age: number;
}>();
</script>

<template>
    <Head :title="t('profile.create.title')" />

    <main
        class="fixed inset-0 mx-auto flex h-svh w-full max-w-xl overflow-hidden px-3 py-[max(0.5rem,env(safe-area-inset-top))] sm:px-6 sm:py-6"
    >
        <Card
            class="flex h-full min-h-0 w-full gap-2 rounded-3xl border-border/70 py-3 shadow-xl shadow-primary/5 sm:gap-4 sm:py-6"
        >
            <CardHeader class="shrink-0 px-4 sm:px-6">
                <CardTitle>{{ t('profile.create.heading') }}</CardTitle>
                <CardDescription>
                    {{ t('profile.create.description') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="min-h-0 flex-1 px-4 sm:px-6">
                <p
                    v-if="avatars.length === 0 && canManageAvatars"
                    class="mb-6 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
                >
                    {{ t('profile.create.empty_catalogue') }}
                    <Link :href="avatarIndex()" class="font-medium underline">
                        {{ t('profile.create.add_first_avatar') }}
                    </Link>
                    {{ t('profile.create.empty_catalogue_suffix') }}
                </p>
                <ProfileForm
                    :profile="profile"
                    :action="store.url()"
                    method="post"
                    :submit-label="t('profile.create.submit')"
                    :visit-frequencies="visitFrequencies"
                    :visibilities="visibilities"
                    :avatars="avatars"
                    :interests="interests"
                    :selected-interest-ids="selectedInterestIds"
                    :interest-limit="interestLimit"
                    :age="age"
                />
            </CardContent>
        </Card>
    </main>
</template>
