<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import BackLink from '@/components/common/BackLink.vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import LikeMemberButton from '@/components/members/LikeMemberButton.vue';
import UnblockMemberButton from '@/components/members/UnblockMemberButton.vue';
import ProfilePresentation from '@/components/profile/ProfilePresentation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { PublicMember } from '@/types';

defineProps<{
    member: PublicMember;
    backHref: string;
    likeHref?: string;
    likeLabel?: string;
    receivedReportsHref?: string;
    canBlock: boolean;
    canLike: boolean;
    canUnblock: boolean;
}>();
const { t } = useTranslations();
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
            as-child
        >
            <BackLink :href="backHref"
                ><ArrowLeft class="size-5" aria-hidden="true"
            /></BackLink>
        </Button>

        <ProfilePresentation
            :avatar="member.avatar"
            :display-name="member.display_name"
            :age="member.age"
            :bio="member.bio ?? t('profile.details.empty_bio')"
            :visit-frequency="member.visit_frequency"
            :social-links="member.social_links ?? []"
            :interests="member.interests"
            :is-admin="member.is_admin"
        >
            <template #summary-actions>
                <Button v-if="receivedReportsHref" as-child variant="outline">
                    <Link
                        :href="receivedReportsHref"
                        data-test="received-reports"
                        >{{ t('moderation.received_reports') }}</Link
                    >
                </Button>
                <LikeMemberButton
                    v-if="canLike"
                    :member-id="member.id"
                    :action-href="likeHref"
                    :label="likeLabel"
                    :return-href="backHref"
                />
                <UnblockMemberButton
                    v-if="canUnblock"
                    :member-id="member.id"
                    :return-href="backHref"
                />
                <BlockMemberDialog
                    v-else-if="canBlock"
                    :member-id="member.id"
                    :return-href="backHref"
                />
            </template>
        </ProfilePresentation>
    </main>
</template>
