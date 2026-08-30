<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import UnblockMemberButton from '@/components/members/UnblockMemberButton.vue';
import ProfilePresentation from '@/components/profile/ProfilePresentation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { PublicMember, VisitFrequency } from '@/types';

const props = defineProps<{
    member: PublicMember;
    backHref: string;
    canUnblock: boolean;
}>();
const { t } = useTranslations();
const frequencyKeys: Record<
    VisitFrequency,
    `profile.details.frequency_${VisitFrequency}`
> = {
    rarely: 'profile.details.frequency_rarely',
    sometimes: 'profile.details.frequency_sometimes',
    often: 'profile.details.frequency_often',
    very_often: 'profile.details.frequency_very_often',
};
const visitFrequency = computed(() =>
    props.member.visit_frequency
        ? t(frequencyKeys[props.member.visit_frequency])
        : t('profile.details.frequency_unknown'),
);

function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(props.backHref);
}
</script>

<template>
    <Head :title="member.display_name" />
    <main
        data-test="public-member-profile"
        class="mx-auto flex h-full min-h-0 w-full max-w-lg flex-col px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:pt-6"
    >
        <Button
            data-test="profile-back-action"
            type="button"
            variant="outline"
            size="icon"
            :aria-label="t('profile.actions.back')"
            class="mb-3 size-11 shrink-0 rounded-full bg-card shadow-md"
            @click="goBack"
        >
            <ArrowLeft class="size-5" aria-hidden="true" />
        </Button>

        <ProfilePresentation
            :avatar="member.avatar"
            :display-name="member.display_name"
            :age-label="t('profile.details.age', { age: member.age })"
            :bio="member.bio ?? t('profile.details.empty_bio')"
            :visit-frequency="visitFrequency"
            :interests="member.interests"
            :about-label="t('profile.details.about')"
            :interests-label="t('profile.details.interests')"
            :visit-frequency-label="t('profile.details.visit_frequency')"
        >
            <template #summary-actions>
                <UnblockMemberButton
                    v-if="canUnblock"
                    :member-id="member.id"
                    :return-href="backHref"
                />
                <BlockMemberDialog
                    v-else
                    :member-id="member.id"
                    :return-href="backHref"
                />
            </template>
        </ProfilePresentation>
    </main>
</template>
