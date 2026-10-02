<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminAccountMenu from '@/components/admin/AdminAccountMenu.vue';
import AdminBottomNavigation from '@/components/admin/AdminBottomNavigation.vue';
import PwaUpdatePrompt from '@/components/pwa/PwaUpdatePrompt.vue';
import WebPushInvitation from '@/components/pwa/WebPushInvitation.vue';
import SeasonalDecorations from '@/components/seasonal/SeasonalDecorations.vue';
import { Toaster } from '@/components/ui/sonner';
import type { TranslationKey } from '@/composables/useTranslations';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    breadcrumbs?: (BreadcrumbItem & { titleKey?: TranslationKey })[];
}>();

const page = usePage();
watch(
    () => page.props.auth.unread_notifications_count,
    (count) => {
        if (!('setAppBadge' in navigator)) {
            return;
        }

        if (count > 0) {
            void navigator.setAppBadge(count).catch(() => undefined);
        } else {
            void navigator.clearAppBadge().catch(() => undefined);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="relative min-h-svh w-full max-w-full min-w-0 overflow-x-hidden bg-background text-foreground"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_42%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_38%)] opacity-20"
        />
        <SeasonalDecorations />
        <AdminAccountMenu />
        <main
            data-test="admin-shell-content"
            class="relative min-h-svh w-full max-w-full min-w-0 overflow-x-hidden pt-14 [padding-bottom:calc(6rem+env(safe-area-inset-bottom))]"
        >
            <slot />
        </main>
        <AdminBottomNavigation />
        <Toaster />
        <PwaUpdatePrompt />
        <WebPushInvitation />
    </div>
</template>
