<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    CalendarDays,
    ChartBar,
    Megaphone,
    MessageCircle,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import BottomNavigation from '@/components/navigation/BottomNavigation.vue';
import WorkspaceSwitcher from '@/components/navigation/WorkspaceSwitcher.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import { useMemberRealtimeContext } from '@/composables/useMemberRealtimeNotifications';
import { useTranslations } from '@/composables/useTranslations';
import { index as conversations } from '@/routes/conversations';
import { index as discovery } from '@/routes/discovery';
import { index as events } from '@/routes/events';
import { show as showProfile } from '@/routes/member-profile';
import { index as notifications } from '@/routes/notifications';
import { index as partnerAnnouncements } from '@/routes/partner/announcements';
import { index as partnerNotifications } from '@/routes/partner/notifications';
import { edit as editPartnerProfile } from '@/routes/partner/profile';
import { index as partnerStatistics } from '@/routes/partner/statistics';
import type { BottomNavigationItem } from '@/types';

const { currentUrl } = useCurrentUrl();
const { t } = useTranslations();
const { unreadNotificationsCount } = useMemberRealtimeContext();
const page = usePage();
const shouldShow = useMemberNavigationVisibility();

const hasPartnerRole = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'partner'),
);
const isPartnerContext = computed(() =>
    currentUrl.value.startsWith('/partner/'),
);

const unreadLabel = computed(() =>
    t('notifications.accessibility.unread_count', {
        count: unreadNotificationsCount.value,
    }),
);
const memberItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('discovery.bottom_navigation'),
        href: discovery(),
        icon: Sparkles,
        activeParents: ['/discover'],
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
        unreadLabel: unreadLabel.value,
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
    {
        label: t('notifications.navigation'),
        href: partnerNotifications(),
        icon: Bell,
        activeParents: ['/partner/notifications'],
        unreadCount: unreadNotificationsCount.value,
        unreadLabel: unreadLabel.value,
    },
]);
const items = computed<BottomNavigationItem[]>(() =>
    isPartnerContext.value && hasPartnerRole.value
        ? partnerItems.value
        : memberItems.value,
);
</script>

<template>
    <BottomNavigation
        v-if="shouldShow"
        :items="items"
        :label="t('common.accessibility.main_navigation')"
        test-id="member-bottom-navigation"
        container-test-id="member-bottom-navigation-container"
    >
        <WorkspaceSwitcher />
    </BottomNavigation>
</template>
