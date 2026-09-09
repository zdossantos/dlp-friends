<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import LikeMemberButton from '@/components/members/LikeMemberButton.vue';
import UnblockMemberButton from '@/components/members/UnblockMemberButton.vue';
import ProfilePresentation from '@/components/profile/ProfilePresentation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { VisitFrequency } from '@/types';
import type { EmbeddedMemberProfile } from '@/types/event';

const props = defineProps<{
    profile: EmbeddedMemberProfile;
    backHref: string;
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
    props.profile.member.visit_frequency
        ? t(frequencyKeys[props.profile.member.visit_frequency])
        : t('profile.details.frequency_unknown'),
);
</script>

<template>
    <section data-test="event-participant-profile" class="space-y-3 pt-1">
        <div class="flex justify-end">
            <Button
                as-child
                type="button"
                variant="outline"
                size="icon"
                class="size-11 rounded-full"
            >
                <Link
                    :href="backHref"
                    preserve-scroll
                    data-test="participant-profile-back"
                    :aria-label="t('events.actions.back')"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                </Link>
            </Button>
        </div>
        <ProfilePresentation
            :avatar="profile.member.avatar"
            :display-name="profile.member.display_name"
            :age-label="t('profile.details.age', { age: profile.member.age })"
            :bio="profile.member.bio ?? t('profile.details.empty_bio')"
            :visit-frequency="visitFrequency"
            :interests="profile.member.interests"
            :about-label="t('profile.details.about')"
            :interests-label="t('profile.details.interests')"
            :visit-frequency-label="t('profile.details.visit_frequency')"
            :is-admin="profile.member.is_admin"
        >
            <template #summary-actions>
                <LikeMemberButton
                    v-if="profile.canLike"
                    :member-id="profile.member.id"
                    :return-href="$page.url"
                />
                <UnblockMemberButton
                    v-if="profile.canUnblock"
                    :member-id="profile.member.id"
                    :return-href="backHref"
                />
                <BlockMemberDialog
                    v-else-if="profile.canBlock"
                    :member-id="profile.member.id"
                    :return-href="backHref"
                />
            </template>
        </ProfilePresentation>
    </section>
</template>
