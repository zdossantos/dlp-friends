<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    MessageCircle,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useTranslations } from '@/composables/useTranslations';
import { index as conversations } from '@/routes/conversations';
import { index as discovery } from '@/routes/discovery';
import { index as events } from '@/routes/events';
import { show as showProfile } from '@/routes/member-profile';
import { index as notifications } from '@/routes/notifications';

const { isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useTranslations();
const { unreadNotificationsCount } = useMemberRealtimeContext();

const shouldShow = useMemberNavigationVisibility();
const pendingPath = ref<string | null>(null);

const items = computed(() => [
    { label: t('discovery.navigation'), href: discovery(), icon: Sparkles },
    {
        label: t('events.navigation'),
        href: events(),
        icon: CalendarDays,
        activeParents: ['/events'],
    },
    {
        label: t('conversations.navigation'),
        href: conversations(),
        icon: MessageCircle,
        activeParents: ['/conversations'],
    },
    {
        label: t('notifications.navigation'),
        href: notifications(),
        icon: Bell,
        activeParents: ['/notifications'],
        unreadCount: unreadNotificationsCount.value,
    },
    {
        label: t('profile.navigation'),
        href: showProfile(),
        icon: UserRound,
        activeParents: ['/settings'],
    },
]);

type NavigationItem = (typeof items.value)[number];

function isActive(item: NavigationItem): boolean {
    return (
        isCurrentOrParentUrl(item.href) ||
        item.activeParents?.some((parent) => isCurrentOrParentUrl(parent)) ===
            true
    );
}

function itemPath(item: NavigationItem): string {
    return new URL(item.href.url, window.location.origin).pathname;
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
        v-if="shouldShow"
        data-test="member-bottom-navigation-container"
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center px-4 pt-2 [padding-bottom:max(0.75rem,env(safe-area-inset-bottom))]"
    >
        <nav
            data-test="member-bottom-navigation"
            :aria-label="t('common.accessibility.main_navigation')"
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
                class="relative grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="[
                    isActive(item) ? 'bg-secondary text-primary' : undefined,
                    pendingPath === itemPath(item)
                        ? 'motion-navigation-pending bg-secondary/70 text-primary'
                        : undefined,
                ]"
            >
                <component :is="item.icon" class="size-6" aria-hidden="true" />
                <span
                    v-if="item.unreadCount && item.unreadCount > 0"
                    data-test="notification-unread-count"
                    class="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-destructive px-1 text-[0.65rem] leading-5 font-bold text-destructive-foreground"
                    :aria-label="
                        t('notifications.accessibility.unread_count', {
                            count: item.unreadCount,
                        })
                    "
                >
                    {{ item.unreadCount > 99 ? '99+' : item.unreadCount }}
                </span>
            </Link>
        </nav>
    </div>
</template>
