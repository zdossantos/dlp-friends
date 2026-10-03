<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { BottomNavigationItem } from '@/types';

withDefaults(
    defineProps<{
        items: BottomNavigationItem[];
        label: string;
        testId?: string;
        containerTestId?: string;
    }>(),
    {
        testId: 'bottom-navigation',
        containerTestId: 'bottom-navigation-container',
    },
);

const { isCurrentOrParentUrl } = useCurrentUrl();
const pendingPath = ref<string | null>(null);

function isActive(item: BottomNavigationItem): boolean {
    return (
        isCurrentOrParentUrl(item.href) ||
        item.activeParents?.some((parent) => isCurrentOrParentUrl(parent)) ===
            true
    );
}

function itemPath(item: BottomNavigationItem): string {
    return new URL(toUrl(item.href), window.location.origin).pathname;
}

const stopStartListener = router.on('start', (event) => {
    pendingPath.value = event.detail.visit.url.pathname;
});
const stopFinishListener = router.on('finish', () => {
    pendingPath.value = null;
});

onBeforeUnmount(() => {
    stopStartListener();
    stopFinishListener();
});
</script>

<template>
    <div
        :data-test="containerTestId"
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center px-4 pt-2 [padding-bottom:max(0.75rem,env(safe-area-inset-bottom))]"
    >
        <nav
            :data-test="testId"
            :aria-label="label"
            class="pointer-events-auto flex min-h-16 w-fit items-center gap-2 rounded-3xl border border-border/80 bg-card/95 px-2 shadow-xl shadow-primary/10 backdrop-blur"
        >
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                :aria-label="item.label"
                :aria-current="isActive(item) ? 'page' : undefined"
                :aria-busy="pendingPath === itemPath(item) ? 'true' : undefined"
                :data-pending="
                    pendingPath === itemPath(item) ? 'true' : undefined
                "
                :data-test="item.testId"
                class="relative grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="[
                    isActive(item)
                        ? 'bg-secondary text-secondary-foreground'
                        : undefined,
                    pendingPath === itemPath(item)
                        ? 'motion-navigation-pending bg-secondary/70 text-secondary-foreground'
                        : undefined,
                ]"
            >
                <component :is="item.icon" class="size-6" aria-hidden="true" />
                <span
                    v-if="item.unreadCount && item.unreadCount > 0"
                    data-test="notification-unread-count"
                    class="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-destructive px-1 text-[0.65rem] leading-5 font-bold text-destructive-foreground"
                    :aria-label="item.unreadLabel"
                >
                    {{ item.unreadCount > 99 ? '99+' : item.unreadCount }}
                </span>
            </Link>
            <slot />
        </nav>
    </div>
</template>
