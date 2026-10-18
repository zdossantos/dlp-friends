<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import MatchDialog from '@/components/discovery/MatchDialog.vue';
import MemberBottomNavigation from '@/components/MemberBottomNavigation.vue';
import PwaUpdatePrompt from '@/components/pwa/PwaUpdatePrompt.vue';
import WebPushInvitation from '@/components/pwa/WebPushInvitation.vue';
import SeasonalDecorations from '@/components/seasonal/SeasonalDecorations.vue';
import { Toaster } from '@/components/ui/sonner';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import {
    provideMemberRealtimeContext,
    useMemberRealtimeNotifications,
} from '@/composables/useMemberRealtimeNotifications';
import { usePwaViewportRecovery } from '@/composables/usePwaViewportRecovery';
import { show as showConversation } from '@/routes/conversations';
import type { DiscoveryMatch } from '@/types';

const reservesMemberNavigation = useMemberNavigationVisibility();
const page = usePage();
usePwaViewportRecovery();
// These views contain cards, a wizard or a message timeline sized to the viewport.
// Other pages use document scrolling so WebKit can size its native scroll view.
const boundedViewport = computed(() =>
    [
        'Discovery/Index',
        'Discovery/Passed',
        'Conversations/Index',
        'Conversations/Show',
        'Conversations/Group',
        'Members/Show',
        'profile/Create',
        'profile/Edit',
        'profile/Show',
    ].includes(page.component),
);
const realtime = useMemberRealtimeNotifications(page.props.auth.user.id);
provideMemberRealtimeContext(realtime);
watch(
    () => page.props.match as DiscoveryMatch | null | undefined,
    (match) => {
        if (!match) {
            return;
        }

        realtime.presentMatch({
            match_id: match.id,
            conversation_id: match.conversationId,
            member: match.member,
        });
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="relative flex w-full max-w-full min-w-0 flex-col bg-background text-foreground"
        :class="boundedViewport ? 'h-dvh overflow-hidden' : 'min-h-dvh'"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_42%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_38%)] opacity-35"
        />
        <SeasonalDecorations />
        <div
            data-test="member-shell-content"
            :scroll-region="boundedViewport ? '' : undefined"
            class="relative flex w-full max-w-full min-w-0 flex-1 flex-col"
            :class="[
                boundedViewport && 'min-h-0 overflow-y-auto overscroll-contain',
                reservesMemberNavigation &&
                    '[padding-bottom:calc(5.5rem+env(safe-area-inset-bottom))]',
            ]"
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
        <PwaUpdatePrompt />
        <WebPushInvitation />
    </div>
</template>
