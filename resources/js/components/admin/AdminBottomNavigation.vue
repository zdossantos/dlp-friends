<script setup lang="ts">
import {
    BookOpen,
    CircleGauge,
    Images,
    Megaphone,
    Palette,
    Shapes,
    Store,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import BottomNavigation from '@/components/navigation/BottomNavigation.vue';
import BottomNavigationGroup from '@/components/navigation/BottomNavigationGroup.vue';
import WorkspaceSwitcher from '@/components/navigation/WorkspaceSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import { index as avatars } from '@/routes/admin/avatars';
import { index as interests } from '@/routes/admin/interests';
import { index as members } from '@/routes/admin/members';
import { index as onboarding } from '@/routes/admin/onboarding';
import { index as partnerAnnouncements } from '@/routes/admin/partner-announcements';
import { index as partnerProfiles } from '@/routes/admin/partner-profiles';
import { index as partnerStatistics } from '@/routes/admin/partner-statistics';
import { index as seasonalThemes } from '@/routes/admin/seasonal-themes';
import type { BottomNavigationItem } from '@/types';

const { t } = useTranslations();

const directItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('administration.navigation.dashboard'),
        href: dashboard(),
        icon: CircleGauge,
        testId: 'admin-dashboard-link',
    },
    {
        label: t('administration.navigation.members'),
        href: members(),
        icon: Users,
        activeParents: ['/admin/members'],
        testId: 'admin-members-link',
    },
]);
const catalogueItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('administration.navigation.interests'),
        href: interests(),
        icon: Shapes,
        testId: 'admin-interests-link',
    },
    {
        label: t('administration.navigation.avatars'),
        href: avatars(),
        icon: Images,
        testId: 'admin-avatars-link',
    },
    {
        label: t('administration.navigation.onboarding'),
        href: onboarding(),
        icon: BookOpen,
        testId: 'admin-onboarding-link',
    },
    {
        label: t('administration.navigation.seasonal_themes'),
        href: seasonalThemes(),
        icon: Palette,
        testId: 'admin-seasonal-themes-link',
    },
]);
const partnerItems = computed<BottomNavigationItem[]>(() => [
    {
        label: t('administration.navigation.partner_profiles'),
        href: partnerProfiles(),
        icon: Store,
        testId: 'admin-partner-profiles-link',
    },
    {
        label: t('administration.navigation.partner_announcements'),
        href: partnerAnnouncements(),
        icon: Megaphone,
        testId: 'admin-partner-announcements-link',
    },
    {
        label: t('administration.navigation.partner_statistics'),
        href: partnerStatistics(),
        icon: CircleGauge,
        testId: 'admin-partner-statistics-link',
    },
]);
</script>

<template>
    <BottomNavigation
        :items="directItems"
        :label="t('administration.navigation.label')"
        test-id="admin-bottom-navigation"
        container-test-id="admin-bottom-navigation-container"
    >
        <BottomNavigationGroup
            :label="t('administration.navigation.catalogues')"
            :icon="Shapes"
            :items="catalogueItems"
            test-id="admin-catalogues-menu"
        />
        <BottomNavigationGroup
            :label="t('administration.navigation.partners')"
            :icon="Store"
            :items="partnerItems"
            test-id="admin-partners-menu"
        />
        <WorkspaceSwitcher />
    </BottomNavigation>
</template>
