<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MemberBottomNavigation from '@/components/MemberBottomNavigation.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const isConversationOpen = computed(() =>
    /^\/conversations\/[^/]+$/.test(page.url.split('?')[0] ?? ''),
);
const reservesMemberNavigation = computed(
    () =>
        Boolean(page.props.auth.user.profile?.onboarding_completed_at) &&
        !page.url.split('?')[0]?.startsWith('/profile/edit') &&
        !isConversationOpen.value,
);
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
        <Toaster />
    </div>
</template>
