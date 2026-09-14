<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageCircle, UserRoundX } from '@lucide/vue';
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
    blockReturnHref: string;
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
    !props.profile.isBlocked && props.profile.member.visit_frequency
        ? t(frequencyKeys[props.profile.member.visit_frequency])
        : t('profile.details.frequency_unknown'),
);
</script>

<template>
    <section data-test="event-participant-profile" class="h-full bg-card">
        <div
            v-if="profile.isBlocked"
            data-test="blocked-participant-profile"
            class="flex min-h-full flex-col items-center justify-center gap-5 bg-muted px-6 py-16 text-center text-muted-foreground"
        >
            <span
                class="grid size-24 place-items-center rounded-full bg-muted-foreground/15"
            >
                <UserRoundX class="size-12" aria-hidden="true" />
            </span>
            <div class="space-y-2">
                <h2 class="text-xl font-semibold text-foreground">
                    {{ t('events.participants.blocked_user') }}
                </h2>
                <p class="max-w-sm text-sm">
                    {{ t('events.participants.blocked_profile_description') }}
                </p>
            </div>
            <UnblockMemberButton
                v-if="profile.canUnblock"
                :member-id="profile.member.id"
                :return-href="$page.url"
                data-test="unblock-member"
            />
        </div>
        <ProfilePresentation
            v-else
            embedded
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
                <Button
                    v-else-if="profile.conversationHref"
                    as-child
                    variant="secondary"
                >
                    <Link :href="profile.conversationHref">
                        <MessageCircle class="size-4" aria-hidden="true" />
                        {{ t('events.participants.discuss') }}
                    </Link>
                </Button>
                <UnblockMemberButton
                    v-if="profile.canUnblock"
                    :member-id="profile.member.id"
                    :return-href="$page.url"
                />
                <BlockMemberDialog
                    v-else-if="profile.canBlock"
                    :member-id="profile.member.id"
                    :return-href="blockReturnHref"
                />
            </template>
        </ProfilePresentation>
    </section>
</template>
