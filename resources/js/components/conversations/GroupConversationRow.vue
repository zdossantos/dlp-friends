<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useConnectionStatus } from '@laravel/echo-vue';
import { Users } from '@lucide/vue';
import { watch } from 'vue';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useTranslations } from '@/composables/useTranslations';
import type { GroupConversationSummary } from '@/types/groupChat';

const props = defineProps<{
    group: GroupConversationSummary;
}>();
const { t } = useTranslations();
const status = useConnectionStatus();
const refresh = () => router.reload({ only: ['groups'] });
const { latestGroupMessage } = useMemberRealtimeContext();
watch(latestGroupMessage, (message) => {
    if (message?.group_chat_id === props.group.id) {
        refresh();
    }
});
watch(status, (next, previous) => {
    if (next === 'connected' && previous !== 'connected') {
        refresh();
    }
});
</script>

<template>
    <Link
        :href="group.href"
        :data-test="`group-conversation-${group.id}`"
        :data-unread="group.unread_count > 0"
        class="flex min-h-20 items-center gap-3 px-4 py-3 hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
        :class="group.unread_count > 0 ? 'bg-primary/8' : ''"
    >
        <span
            class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary/10"
            ><Users class="size-6 text-primary" aria-hidden="true"
        /></span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold">{{ group.title }}</span>
            <span class="text-sm text-muted-foreground">{{
                t(
                    group.isReadOnly
                        ? 'conversations.groups.read_only'
                        : group.context === 'trip'
                          ? 'conversations.groups.trip_chat'
                          : 'conversations.groups.event_chat',
                )
            }}</span>
        </span>
        <span
            v-if="group.unread_count > 0"
            class="rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground"
            :aria-label="
                t(
                    group.unread_count === 1
                        ? 'conversations.page.unread_message'
                        : 'conversations.page.unread_messages',
                    { count: group.unread_count },
                )
            "
            >{{ group.unread_count }}</span
        >
    </Link>
</template>
