<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartBar,
    GraduationCap,
    Images,
    LayoutDashboard,
    Building2,
    Megaphone,
    Tags,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import { index as avatarIndex } from '@/routes/admin/avatars';
import { index as interestIndex } from '@/routes/admin/interests';
import { index as memberIndex } from '@/routes/admin/members';
import { index as onboardingIndex } from '@/routes/admin/onboarding';
import { index as adminPartnerAnnouncements } from '@/routes/admin/partner-announcements';
import { index as partnerProfileIndex } from '@/routes/admin/partner-profiles';
import { index as adminPartnerStatistics } from '@/routes/admin/partner-statistics';
import { show as showProfile } from '@/routes/member-profile';
import { index as partnerAnnouncements } from '@/routes/partner/announcements';
import { edit as editPartnerProfile } from '@/routes/partner/profile';
import { index as partnerStatistics } from '@/routes/partner/statistics';
import type { NavItem } from '@/types';

const { t } = useTranslations();
const page = usePage();
const hasPartnerRole = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'partner'),
);

const mainNavItems: NavItem[] = [
    {
        title: t('administration.navigation.dashboard'),
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: t('administration.navigation.members'),
        href: memberIndex(),
        icon: Users,
    },
    {
        title: t('administration.navigation.interests'),
        href: interestIndex(),
        icon: Tags,
    },
    {
        title: t('administration.navigation.avatars'),
        href: avatarIndex(),
        icon: Images,
    },
    {
        title: t('administration.navigation.onboarding'),
        href: onboardingIndex(),
        icon: GraduationCap,
    },
    {
        title: t('administration.navigation.partners'),
        href: partnerProfileIndex(),
        icon: Building2,
        testId: 'admin-partners-menu-trigger',
        items: [
            {
                title: t('administration.navigation.partner_profiles'),
                href: partnerProfileIndex(),
                icon: Building2,
            },
            {
                title: t('administration.navigation.partner_announcements'),
                href: adminPartnerAnnouncements(),
                icon: Megaphone,
            },
            {
                title: t('administration.navigation.partner_statistics'),
                href: adminPartnerStatistics(),
                icon: ChartBar,
            },
        ],
    },
    {
        title: t('administration.navigation.back_to_profile'),
        href: showProfile(),
        icon: UserRound,
    },
];

const partnerNavItems: NavItem[] = [
    {
        title: t('partners.navigation.profile'),
        href: editPartnerProfile(),
        icon: Building2,
    },
    {
        title: t('partners.navigation.announcements'),
        href: partnerAnnouncements(),
        icon: Megaphone,
    },
    {
        title: t('partners.navigation.statistics'),
        href: partnerStatistics(),
        icon: ChartBar,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                :items="mainNavItems"
                :label="t('administration.navigation.label')"
            />
            <NavMain
                v-if="hasPartnerRole"
                :items="partnerNavItems"
                :label="t('partners.navigation.label')"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
