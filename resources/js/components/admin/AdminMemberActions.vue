<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import DeleteMemberDialog from '@/components/admin/DeleteMemberDialog.vue';
import ManageMemberRolesDialog from '@/components/admin/ManageMemberRolesDialog.vue';
import SetMemberBanDialog from '@/components/admin/SetMemberBanDialog.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index as reports } from '@/routes/admin/conversation-reports';
import type { AdminMember } from '@/types/admin';

defineProps<{ member: AdminMember; conversationSize?: 'sm' }>();
const emit = defineEmits<{ startConversation: [member: AdminMember] }>();
const { t } = useTranslations();
</script>

<template>
    <Button as-child variant="outline"
        ><Link
            :href="reports({ query: { target: member.id } }).url"
            data-test="received-reports"
            >{{ t('moderation.received_reports') }}</Link
        ></Button
    >
    <ManageMemberRolesDialog
        v-if="member.can_manage_roles"
        :member-id="member.id"
        :display-name="member.display_name ?? member.email"
        :roles="member.roles"
    />
    <Button
        v-if="member.can_start_conversation"
        type="button"
        :size="conversationSize"
        variant="outline"
        data-test="start-member-conversation"
        @click="emit('startConversation', member)"
    >
        <MessageCircle class="size-4" aria-hidden="true" />
        {{ t('administration.members.conversation') }}
    </Button>
    <SetMemberBanDialog
        v-if="member.can_ban"
        :member-id="member.id"
        :banned="member.status === 'banned'"
    />
    <DeleteMemberDialog
        v-if="member.can_delete"
        :member-id="member.id"
        :display-name="member.display_name ?? member.email"
    />
</template>
