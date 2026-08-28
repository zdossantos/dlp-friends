<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    `blocking.frequency_${VisitFrequency}`
> = {
    rarely: 'blocking.frequency_rarely',
    sometimes: 'blocking.frequency_sometimes',
    often: 'blocking.frequency_often',
    very_often: 'blocking.frequency_very_often',
};
const visitFrequency = computed(() =>
    props.member.visit_frequency
        ? t(frequencyKeys[props.member.visit_frequency])
        : t('blocking.frequency_unknown'),
);
</script>

<template>
    <Head :title="member.display_name" />
    <main
        data-test="public-member-profile"
        class="mx-auto flex h-full min-h-0 w-full max-w-lg flex-col px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:pt-6"
    >
        <Button
            data-test="profile-back-action"
            as-child
            variant="ghost"
            class="mb-2 w-fit shrink-0"
        >
            <Link :href="backHref">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('navigation.discovery') }}
            </Link>
        </Button>

        <ProfilePresentation
            :avatar="member.avatar"
            :display-name="member.display_name"
            :age-label="t('blocking.age', { age: member.age })"
            :bio="member.bio ?? t('blocking.empty_bio')"
            :visit-frequency="visitFrequency"
            :interests="member.interests"
            :about-label="t('blocking.about')"
            :interests-label="t('blocking.interests')"
            :visit-frequency-label="t('blocking.visit_frequency')"
        >
            <template #summary-actions>
                <UnblockMemberButton v-if="canUnblock" :member-id="member.id" />
                <BlockMemberDialog v-else :member-id="member.id" />
            </template>
        </ProfilePresentation>
    </main>
</template>
