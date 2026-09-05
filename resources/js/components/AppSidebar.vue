<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    GraduationCap,
    Images,
    LayoutDashboard,
    Tags,
    UserRound,
    Users,
} from '@lucide/vue';
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
import { show as showProfile } from '@/routes/member-profile';
import type { NavItem } from '@/types';

const { t } = useTranslations();

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
        title: t('administration.navigation.back_to_profile'),
        href: showProfile(),
        icon: UserRound,
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
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
