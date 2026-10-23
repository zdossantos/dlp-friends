<script setup lang="ts">
import { Head, Link, router, usePage, useRemember } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed, ref, toRefs, unref, watch } from 'vue';
import ActivityStatus from '@/components/conversations/ActivityStatus.vue';
import GroupConversationRow from '@/components/conversations/GroupConversationRow.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import SeasonalDecorations from '@/components/seasonal/SeasonalDecorations.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useMessagingRefresh } from '@/composables/useMessagingRefresh';
import { useTranslations } from '@/composables/useTranslations';
import { useConversationListTyping } from '@/composables/useTypingSignals';
import {
    applyConversationMessage,
    conversationPreview,
    sortConversationSummaries,
} from '@/lib/conversationState';
import { normalizeSearchText } from '@/lib/textSearch';
import { show as showConversation } from '@/routes/conversations';
import type { ConversationSummary } from '@/types';
import type { GroupConversationSummary } from '@/types/groupChat';

const props = defineProps<{
    conversations: ConversationSummary[];
    currentUserId: number;
    groups: GroupConversationSummary[];
}>();
useMessagingRefresh(() =>
    router.reload({ only: ['conversations', 'groups', 'auth'] }),
);
const visibleConversations = ref(
    sortConversationSummaries(props.conversations),
);
const { t } = useTranslations();
const { activeMatch, latestMessage, latestGroupMessage, presenceChanged } =
    useMemberRealtimeContext();
const typingIds = useConversationListTyping(
    props.conversations.filter((conversation) => !conversation.archived_at),
);
const filters = useRemember(
    {
        category:
            new URL(usePage().url, window.location.origin).searchParams.get(
                'category',
            ) === 'groups'
                ? 'groups'
                : 'private',
        search: '',
    },
    'conversations-filters',
);
const { category, search } = toRefs(unref(filters));
const privateUnreadCount = computed(() =>
    visibleConversations.value.reduce(
        (total, conversation) => total + conversation.unread_count,
        0,
    ),
);
const groupUnreadCount = computed(() =>
    props.groups.reduce((total, group) => total + group.unread_count, 0),
);
watch(latestGroupMessage, () => router.reload({ only: ['groups'] }));
const normalizedSearch = computed(() => normalizeSearchText(search.value));
const filteredConversations = computed(() => {
    if (normalizedSearch.value === '') {
        return visibleConversations.value;
    }

    return visibleConversations.value.filter((conversation) =>
        normalizeSearchText(conversation.participant.display_name).includes(
            normalizedSearch.value,
        ),
    );
});

function unreadLabel(count: number): string {
    return t(
        count === 1
            ? 'conversations.page.unread_message'
            : 'conversations.page.unread_messages',
        { count },
    );
}

watch(
    () => props.conversations,
    (conversations) => {
        visibleConversations.value = sortConversationSummaries(conversations);
    },
    { deep: true },
);

watch(category, (next) => {
    if (next === 'groups') {
        router.reload({ only: ['groups'] });
    }
});

watch(latestMessage, (message) => {
    if (message) {
        visibleConversations.value = applyConversationMessage(
            visibleConversations.value,
            message,
            props.currentUserId,
        );
    }
});
watch(activeMatch, (match) => {
    if (match) {
        router.reload({ only: ['conversations'] });
    }
});
watch(presenceChanged, (event) => {
    if (!event) {
        return;
    }

    visibleConversations.value = visibleConversations.value.map(
        (conversation) =>
            conversation.participant.id === event.user_id
                ? {
                      ...conversation,
                      participant: {
                          ...conversation.participant,
                          presence: {
                              online: event.online,
                              last_active_at: event.last_active_at,
                          },
                      },
                  }
                : conversation,
    );
});
</script>

<template>
    <Head :title="t('conversations.page.title')" />

    <main
        class="mx-auto flex h-full min-h-0 w-full max-w-2xl min-w-0 flex-col gap-6 overflow-hidden px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ t('conversations.page.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('conversations.page.messaging_description') }}
            </p>
        </header>

        <Tabs v-model="category" class="flex min-h-0 flex-1 flex-col gap-4">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="private" data-test="conversations-private">
                    {{ t('conversations.groups.private') }}
                    <span
                        v-if="privateUnreadCount > 0"
                        data-test="private-unread-count"
                        :aria-label="unreadLabel(privateUnreadCount)"
                        class="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                        >{{
                            privateUnreadCount > 99 ? '99+' : privateUnreadCount
                        }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="groups" data-test="conversations-groups">
                    {{ t('conversations.groups.title') }}
                    <span
                        v-if="groupUnreadCount > 0"
                        data-test="group-unread-count"
                        :aria-label="unreadLabel(groupUnreadCount)"
                        class="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                        >{{
                            groupUnreadCount > 99 ? '99+' : groupUnreadCount
                        }}</span
                    >
                </TabsTrigger>
            </TabsList>
            <TabsContent
                value="private"
                class="flex min-h-0 flex-1 flex-col gap-4"
            >
                <div v-if="visibleConversations.length > 0" class="relative">
                    <Input
                        v-model="search"
                        data-test="conversation-search"
                        type="search"
                        :placeholder="
                            t('conversations.page.search_placeholder')
                        "
                        :aria-label="t('conversations.page.search_label')"
                    />
                </div>

                <section
                    v-if="visibleConversations.length === 0"
                    class="relative isolate overflow-hidden rounded-3xl border bg-card p-6 text-center shadow-sm"
                >
                    <SeasonalDecorations placement="panel" />
                    <h2 class="font-semibold">
                        {{ t('conversations.page.empty_title') }}
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('conversations.page.empty_description') }}
                    </p>
                </section>

                <section
                    v-else-if="filteredConversations.length === 0"
                    class="relative isolate overflow-hidden rounded-3xl border bg-card p-6 text-center shadow-sm"
                >
                    <SeasonalDecorations placement="panel" />
                    <p class="font-semibold">
                        {{ t('conversations.page.search_empty') }}
                    </p>
                    <Button
                        type="button"
                        variant="link"
                        data-test="clear-conversation-search"
                        class="mt-3 h-auto p-0 text-sm"
                        @click="search = ''"
                    >
                        {{ t('conversations.page.search_clear') }}
                    </Button>
                </section>

                <section
                    v-else
                    :aria-label="t('conversations.page.list_label')"
                    class="relative isolate min-h-0 max-w-full min-w-0 flex-1 overflow-y-auto overscroll-contain rounded-3xl border bg-card shadow-sm"
                >
                    <SeasonalDecorations placement="panel" />
                    <ul
                        role="list"
                        class="relative z-10 max-w-full min-w-0 divide-y"
                    >
                        <li
                            v-for="conversation in filteredConversations"
                            :key="conversation.id"
                        >
                            <Link
                                :href="showConversation(conversation.id)"
                                :data-unread="conversation.unread_count > 0"
                                class="flex min-h-20 max-w-full min-w-0 items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset motion-reduce:transition-none"
                                :class="
                                    conversation.unread_count > 0
                                        ? 'bg-primary/8'
                                        : ''
                                "
                            >
                                <span class="shrink-0">
                                    <AvatarPortrait
                                        :avatar="
                                            conversation.participant.avatar
                                        "
                                        class="size-12 rounded-2xl"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="truncate"
                                            :class="
                                                conversation.unread_count > 0
                                                    ? 'font-bold'
                                                    : 'font-semibold'
                                            "
                                        >
                                            {{
                                                conversation.participant
                                                    .display_name
                                            }}
                                        </span>
                                        <span
                                            v-if="conversation.unread_count > 0"
                                            class="grid min-w-5 shrink-0 place-items-center rounded-full bg-primary px-1.5 py-0.5 text-xs font-bold text-primary-foreground"
                                            :aria-label="
                                                unreadLabel(
                                                    conversation.unread_count,
                                                )
                                            "
                                        >
                                            {{ conversation.unread_count }}
                                        </span>
                                        <span
                                            v-else-if="
                                                conversation.latest_message ===
                                                    null &&
                                                conversation.archived_at ===
                                                    null
                                            "
                                            data-test="new-conversation-label"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full border border-primary/30 px-2 py-0.5 text-xs font-medium text-primary"
                                        >
                                            <MessageCircle
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'conversations.page.new_exchange',
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="conversation.archived_at"
                                            class="shrink-0 text-xs font-medium text-muted-foreground"
                                        >
                                            {{
                                                t('conversations.page.archived')
                                            }}
                                        </span>
                                    </span>
                                    <ActivityStatus
                                        v-if="
                                            typingIds.has(conversation.id) ||
                                            conversation.participant.presence
                                        "
                                        :presence="
                                            conversation.participant.presence
                                        "
                                        :typing="typingIds.has(conversation.id)"
                                    />
                                    <span
                                        v-if="!typingIds.has(conversation.id)"
                                        data-test="conversation-preview"
                                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                                    >
                                        {{
                                            conversationPreview(
                                                conversation,
                                                currentUserId,
                                                t(
                                                    conversation.archived_at
                                                        ? 'conversations.page.new_exchange'
                                                        : 'conversations.page.start_exchange',
                                                ),
                                                t(
                                                    'conversations.page.current_user_prefix',
                                                ),
                                            )
                                        }}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </TabsContent>
            <TabsContent
                value="groups"
                class="min-h-0 flex-1 overflow-y-auto rounded-3xl border bg-card shadow-sm"
            >
                <ul
                    v-if="groups.length"
                    class="divide-y"
                    :aria-label="t('conversations.groups.title')"
                >
                    <li v-for="group in groups" :key="group.id">
                        <GroupConversationRow :group="group" />
                    </li>
                </ul>
                <p v-else class="p-6 text-center text-sm text-muted-foreground">
                    {{ t('conversations.groups.empty') }}
                </p>
            </TabsContent>
        </Tabs>
    </main>
</template>
