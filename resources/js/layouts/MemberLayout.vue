<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import MatchDialog from '@/components/discovery/MatchDialog.vue';
import MemberBottomNavigation from '@/components/MemberBottomNavigation.vue';
import { Toaster } from '@/components/ui/sonner';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import {
    provideMemberRealtimeContext,
    useMemberRealtimeNotifications,
} from '@/composables/useMemberRealtimeNotifications';
import { show as showConversation } from '@/routes/conversations';

const reservesMemberNavigation = useMemberNavigationVisibility();
const page = usePage();
const realtime = useMemberRealtimeNotifications(page.props.auth.user.id);
provideMemberRealtimeContext(realtime);
</script>

<template>
    <div
        class="relative flex h-svh w-full flex-col overflow-hidden bg-background text-foreground"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_42%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_38%)] opacity-35"
        />
        <div
            data-test="member-shell-content"
            class="relative flex min-h-0 w-full flex-1 flex-col overflow-y-auto overscroll-contain"
            :class="
                reservesMemberNavigation
                    ? '[padding-bottom:calc(5.5rem+env(safe-area-inset-bottom))]'
                    : undefined
            "
        >
            <slot />
        </div>
        <MemberBottomNavigation />
        <MatchDialog
            v-if="realtime.activeMatch.value"
            :open="true"
            :match="realtime.activeMatch.value.member"
            :conversation-href="
                showConversation(realtime.activeMatch.value.conversation_id).url
            "
            @update:open="realtime.dismissMatch"
        />
        <Toaster />
    </div>
</template>
