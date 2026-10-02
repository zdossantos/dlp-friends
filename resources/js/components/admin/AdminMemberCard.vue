<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import DeleteMemberDialog from '@/components/admin/DeleteMemberDialog.vue';
import ManageMemberRolesDialog from '@/components/admin/ManageMemberRolesDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { RoleName } from '@/types';

type Member = {
    id: number;
    display_name: string | null;
    email: string;
    status: string;
    visibility: string | null;
    created_at: string | null;
    email_verified_at: string | null;
    is_admin: boolean;
    roles: Array<{ name: RoleName }>;
    likes_sent_count: number;
    likes_received_count: number;
    passes_sent_count: number;
    passes_received_count: number;
    matches_count: number;
    messages_sent_count: number;
    blocked_count: number;
    blocked_by_count: number;
    can_delete: boolean;
    can_start_conversation: boolean;
    can_manage_roles: boolean;
};

const props = defineProps<{ member: Member }>();
const emit = defineEmits<{ startConversation: [member: Member] }>();
const { formatDate, t } = useTranslations();

function date(value: string | null): string {
    return value
        ? formatDate(value, { dateStyle: 'short' })
        : t('administration.members.not_verified');
}
</script>

<template>
    <Card class="min-w-0" data-test="admin-member-card">
        <CardHeader class="min-w-0 gap-2">
            <CardTitle class="text-lg break-words">
                {{
                    member.display_name ??
                    t('administration.members.incomplete_profile')
                }}
            </CardTitle>
            <p class="text-sm break-all text-muted-foreground">
                {{ member.email }}
            </p>
            <Badge v-if="member.is_admin" variant="outline" class="w-fit">
                {{ t('profile.details.administrator') }}
            </Badge>
        </CardHeader>
        <CardContent class="grid min-w-0 gap-4 text-sm">
            <dl class="grid min-w-0 grid-cols-2 gap-x-3 gap-y-3">
                <div class="col-span-2 min-w-0">
                    <dt class="font-medium">
                        {{ t('administration.members.account') }}
                    </dt>
                    <dd class="break-words text-muted-foreground">
                        {{
                            t(`administration.members.status_${member.status}`)
                        }}
                        ·
                        {{
                            member.visibility
                                ? t(
                                      `administration.members.visibility_${member.visibility}`,
                                  )
                                : t('administration.members.incomplete_profile')
                        }}
                    </dd>
                    <dd class="break-words text-muted-foreground">
                        {{
                            t('administration.members.registered', {
                                date: date(member.created_at),
                            })
                        }}
                    </dd>
                    <dd class="break-words text-muted-foreground">
                        {{
                            t('administration.members.verified', {
                                date: date(member.email_verified_at),
                            })
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ t('administration.members.likes') }}
                    </dt>
                    <dd class="text-muted-foreground">
                        {{
                            t('administration.members.sent_received', {
                                sent: member.likes_sent_count,
                                received: member.likes_received_count,
                            })
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ t('administration.members.passes') }}
                    </dt>
                    <dd class="text-muted-foreground">
                        {{
                            t('administration.members.sent_received', {
                                sent: member.passes_sent_count,
                                received: member.passes_received_count,
                            })
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ t('administration.members.matches') }}
                    </dt>
                    <dd class="text-muted-foreground">
                        {{ member.matches_count }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ t('administration.members.messages') }}
                    </dt>
                    <dd class="text-muted-foreground">
                        {{ member.messages_sent_count }}
                    </dd>
                </div>
                <div class="col-span-2">
                    <dt class="font-medium">
                        {{ t('administration.members.blocks') }}
                    </dt>
                    <dd class="text-muted-foreground">
                        {{
                            t('administration.members.block_stats', {
                                blocked: member.blocked_count,
                                received: member.blocked_by_count,
                            })
                        }}
                    </dd>
                </div>
            </dl>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:flex-wrap [&>*]:min-h-11"
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
                    variant="outline"
                    data-test="start-member-conversation"
                    @click="emit('startConversation', props.member)"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    {{ t('administration.members.conversation') }}
                </Button>
                <DeleteMemberDialog
                    v-if="member.can_delete"
                    :member-id="member.id"
                    :display-name="member.display_name ?? member.email"
                />
            </div>
        </CardContent>
    </Card>
</template>
