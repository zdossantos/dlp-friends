<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Bell,
    Building2,
    CalendarDays,
    ChartBar,
    Check,
    Megaphone,
    MessageCircle,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { index as conversations } from '@/routes/conversations';
import { index as discovery } from '@/routes/discovery';
import { index as events } from '@/routes/events';
import { show as showProfile } from '@/routes/member-profile';
import { index as notifications } from '@/routes/notifications';
import { index as partnerAnnouncements } from '@/routes/partner/announcements';
import { edit as editPartnerProfile } from '@/routes/partner/profile';
import { index as partnerStatistics } from '@/routes/partner/statistics';

const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useTranslations();
const { unreadNotificationsCount } = useMemberRealtimeContext();
const page = usePage();

const shouldShow = useMemberNavigationVisibility();
const pendingPath = ref<string | null>(null);
const hasPartnerRole = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'partner'),
);
const hasMemberRole = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'user'),
);
const canSwitchWorkspace = computed(
    () => hasMemberRole.value && hasPartnerRole.value,
);
const isPartnerContext = computed(() =>
    currentUrl.value.startsWith('/partner/'),
);

type BottomNavigationItem = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    activeParents?: string[];
    unreadCount?: number;
};

const memberItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('discovery.bottom_navigation'),
        href: discovery(),
        icon: Sparkles,
    },
    {
        label: t('conversations.bottom_navigation'),
        href: conversations(),
        icon: MessageCircle,
        activeParents: ['/conversations'],
    },
    {
        label: t('events.navigation'),
        href: events(),
        icon: CalendarDays,
        activeParents: ['/events'],
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

const partnerItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('partners.navigation.profile'),
        href: editPartnerProfile(),
        icon: Building2,
    },
    {
        label: t('partners.navigation.announcements'),
        href: partnerAnnouncements(),
        icon: Megaphone,
        activeParents: ['/partner/announcements'],
    },
    {
        label: t('partners.navigation.statistics'),
        href: partnerStatistics(),
        icon: ChartBar,
        activeParents: ['/partner/statistics'],
    },
]);

const items = computed<BottomNavigationItem[]>(() =>
    isPartnerContext.value && hasPartnerRole.value
        ? partnerItems.value
        : memberItems.value,
);

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
                    :aria-label="
                        t('notifications.accessibility.unread_count', {
                            count: item.unreadCount,
                        })
                    "
                >
                    {{ item.unreadCount > 99 ? '99+' : item.unreadCount }}
                </span>
            </Link>
            <Sheet v-if="canSwitchWorkspace">
                <SheetTrigger :as-child="true">
                    <button
                        type="button"
                        data-test="workspace-switcher-trigger"
                        :aria-label="t('common.workspace_switcher.trigger')"
                        class="relative grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ArrowLeftRight class="size-6" aria-hidden="true" />
                    </button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    class="rounded-t-3xl px-4 pt-2 [padding-bottom:max(1.5rem,env(safe-area-inset-bottom))]"
                >
                    <SheetHeader class="px-0 text-left">
                        <SheetTitle>
                            {{ t('common.workspace_switcher.title') }}
                        </SheetTitle>
                        <SheetDescription>
                            {{ t('common.workspace_switcher.description') }}
                        </SheetDescription>
                    </SheetHeader>

                    <div class="grid gap-2">
                        <SheetClose :as-child="true">
                            <Link
                                :href="discovery()"
                                data-test="workspace-member-link"
                                :aria-current="
                                    !isPartnerContext ? 'page' : undefined
                                "
                                class="flex items-center gap-3 rounded-2xl border border-border p-4 transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <UserRound
                                    class="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="flex-1 font-medium">
                                    {{ t('common.workspace_switcher.member') }}
                                </span>
                                <Check
                                    v-if="!isPartnerContext"
                                    class="size-5 text-primary"
                                    aria-hidden="true"
                                />
                            </Link>
                        </SheetClose>
                        <SheetClose :as-child="true">
                            <Link
                                :href="editPartnerProfile()"
                                data-test="workspace-partner-link"
                                :aria-current="
                                    isPartnerContext ? 'page' : undefined
                                "
                                class="flex items-center gap-3 rounded-2xl border border-border p-4 transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Building2
                                    class="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="flex-1 font-medium">
                                    {{ t('common.workspace_switcher.partner') }}
                                </span>
                                <Check
                                    v-if="isPartnerContext"
                                    class="size-5 text-primary"
                                    aria-hidden="true"
                                />
                            </Link>
                        </SheetClose>
                    </div>
                </SheetContent>
            </Sheet>
        </nav>
    </div>
</template>
