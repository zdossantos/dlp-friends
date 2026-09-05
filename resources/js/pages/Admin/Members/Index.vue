<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import DeleteMemberDialog from '@/components/admin/DeleteMemberDialog.vue';
import MatchDialog from '@/components/discovery/MatchDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/admin/members';
import { store as openConversation } from '@/routes/admin/members/conversation';

type Member = {
    id: number;
    display_name: string | null;
    email: string;
    status: string;
    visibility: string | null;
    created_at: string | null;
    email_verified_at: string | null;
    is_admin: boolean;
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
};
type PageLink = { url: string | null; label: string; active: boolean };
type CreatedMatch = { displayName: string; conversationHref: string };
const props = defineProps<{
    filters: { search: string };
    members: { data: Member[]; total: number; links: PageLink[] };
    createdMatch: CreatedMatch | null;
}>();
const { formatDate, t } = useTranslations();
const search = ref(props.filters.search);
const visibleCreatedMatch = ref(props.createdMatch);
const matchDialogOpen = ref(props.createdMatch !== null);

watch(
    () => props.createdMatch,
    (createdMatch) => {
        if (createdMatch !== null) {
            visibleCreatedMatch.value = createdMatch;
            matchDialogOpen.value = true;
        }
    },
);

function submitSearch(): void {
    router.get(
        index().url,
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
function startConversation(member: Member): void {
    router.post(openConversation(member.id).url, {}, { preserveState: false });
}
function date(value: string | null): string {
    return value
        ? formatDate(value, { dateStyle: 'short' })
        : t('administration.members.not_verified');
}
</script>

<template>
    <Head :title="t('administration.members.title')" />
    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ t('administration.members.title') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ t('administration.members.description') }}
            </p>
        </header>
        <Card>
            <CardHeader
                ><CardTitle>{{
                    t('administration.members.count', { count: members.total })
                }}</CardTitle></CardHeader
            >
            <CardContent>
                <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
                    <Input
                        v-model="search"
                        :placeholder="
                            t('administration.members.search_placeholder')
                        "
                        data-test="member-search"
                    />
                    <Button type="submit">{{
                        t('administration.members.search')
                    }}</Button>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[90rem] text-left text-sm">
                        <thead class="border-b text-muted-foreground">
                            <tr>
                                <th class="p-2">
                                    {{ t('administration.members.member') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.account') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.likes') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.passes') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.matches') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.messages') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.blocks') }}
                                </th>
                                <th class="p-2">
                                    {{ t('administration.members.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in members.data"
                                :key="member.id"
                                class="border-b"
                                data-test="admin-member-row"
                            >
                                <td class="p-2">
                                    <div class="font-medium">
                                        {{
                                            member.display_name ??
                                            t(
                                                'administration.members.incomplete_profile',
                                            )
                                        }}
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{ member.email }}
                                    </div>
                                    <Badge
                                        v-if="member.is_admin"
                                        variant="outline"
                                        >{{
                                            t('profile.details.administrator')
                                        }}</Badge
                                    >
                                </td>
                                <td class="p-2">
                                    {{
                                        t(
                                            `administration.members.status_${member.status}`,
                                        )
                                    }}
                                    ·
                                    {{
                                        member.visibility
                                            ? t(
                                                  `administration.members.visibility_${member.visibility}`,
                                              )
                                            : t(
                                                  'administration.members.incomplete_profile',
                                              )
                                    }}
                                    <div>
                                        {{
                                            t(
                                                'administration.members.registered',
                                                {
                                                    date: date(
                                                        member.created_at,
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                    <div>
                                        {{
                                            t(
                                                'administration.members.verified',
                                                {
                                                    date: date(
                                                        member.email_verified_at,
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="p-2">
                                    {{
                                        t(
                                            'administration.members.sent_received',
                                            {
                                                sent: member.likes_sent_count,
                                                received:
                                                    member.likes_received_count,
                                            },
                                        )
                                    }}
                                </td>
                                <td class="p-2">
                                    {{
                                        t(
                                            'administration.members.sent_received',
                                            {
                                                sent: member.passes_sent_count,
                                                received:
                                                    member.passes_received_count,
                                            },
                                        )
                                    }}
                                </td>
                                <td class="p-2">{{ member.matches_count }}</td>
                                <td class="p-2">
                                    {{ member.messages_sent_count }}
                                </td>
                                <td class="p-2">
                                    {{
                                        t(
                                            'administration.members.block_stats',
                                            {
                                                blocked: member.blocked_count,
                                                received:
                                                    member.blocked_by_count,
                                            },
                                        )
                                    }}
                                </td>
                                <td class="p-2">
                                    <div class="flex gap-2">
                                        <Button
                                            v-if="member.can_start_conversation"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            data-test="start-member-conversation"
                                            @click="startConversation(member)"
                                            ><MessageCircle
                                                class="size-4"
                                                aria-hidden="true"
                                            />{{
                                                t(
                                                    'administration.members.conversation',
                                                )
                                            }}</Button
                                        >
                                        <DeleteMemberDialog
                                            v-if="member.can_delete"
                                            :member-id="member.id"
                                            :display-name="
                                                member.display_name ??
                                                member.email
                                            "
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav
                    class="mt-4 flex flex-wrap gap-2"
                    :aria-label="t('administration.members.pagination')"
                >
                    <Button
                        v-for="link in members.links"
                        :key="link.label"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                        ><Link v-if="link.url" :href="link.url" preserve-state
                            ><span v-html="link.label" /></Link
                        ><span v-else v-html="link.label"
                    /></Button>
                </nav>
            </CardContent>
        </Card>
        <MatchDialog
            v-if="visibleCreatedMatch"
            v-model:open="matchDialogOpen"
            :match="{ displayName: visibleCreatedMatch.displayName }"
            :conversation-href="visibleCreatedMatch.conversationHref"
        />
    </main>
</template>
