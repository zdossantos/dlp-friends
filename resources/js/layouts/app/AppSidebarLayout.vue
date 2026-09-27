<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import PwaUpdatePrompt from '@/components/pwa/PwaUpdatePrompt.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

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
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="overflow-x-hidden bg-background/80"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
        <PwaUpdatePrompt />
    </AppShell>
</template>
