<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, Building2, ShieldCheck, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import NavigationSheetLink from '@/components/navigation/NavigationSheetLink.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import { index as discovery } from '@/routes/discovery';
import { edit as editPartnerProfile } from '@/routes/partner/profile';
import type { RoleName, WorkspaceDestination } from '@/types';

const page = usePage();
const { currentUrl } = useCurrentUrl();
const { t } = useTranslations();

const roles = computed<RoleName[]>(() =>
    page.props.auth.user.roles.map((role) => role.name),
);
const currentRole = computed<RoleName>(() => {
    if (
        currentUrl.value === '/dashboard' ||
        currentUrl.value.startsWith('/admin/')
    ) {
        return 'admin';
    }

    return currentUrl.value.startsWith('/partner/') ? 'partner' : 'user';
});
const destinations = computed<WorkspaceDestination[]>(() =>
    [
        {
            role: 'user' as const,
            label: t('common.workspace_switcher.member'),
            href: discovery(),
            icon: UserRound,
            testId: 'workspace-member-link',
        },
        {
            role: 'partner' as const,
            label: t('common.workspace_switcher.partner'),
            href: editPartnerProfile(),
            icon: Building2,
            testId: 'workspace-partner-link',
        },
        {
            role: 'admin' as const,
            label: t('common.workspace_switcher.admin'),
            href: dashboard(),
            icon: ShieldCheck,
            testId: 'workspace-admin-link',
        },
    ].filter((destination) => roles.value.includes(destination.role)),
);
const destinationTestIds = computed(() =>
    destinations.value
        .map((destination) => destination.testId)
        .sort()
        .join(','),
);
const currentDestinationTestId = computed(
    () =>
        destinations.value.find(
            (destination) => destination.role === currentRole.value,
        )?.testId,
);
</script>

<template>
    <Sheet v-if="destinations.length > 1">
        <SheetTrigger :as-child="true">
            <button
                type="button"
                data-test="workspace-switcher-trigger"
                :data-workspaces="destinationTestIds"
                :data-current-workspace="currentDestinationTestId"
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
                <SheetTitle>{{
                    t('common.workspace_switcher.title')
                }}</SheetTitle>
                <SheetDescription>{{
                    t('common.workspace_switcher.description')
                }}</SheetDescription>
            </SheetHeader>
            <div class="grid gap-2">
                <NavigationSheetLink
                    v-for="destination in destinations"
                    :key="destination.role"
                    :item="destination"
                    :active="destination.role === currentRole"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
