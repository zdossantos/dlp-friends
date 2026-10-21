<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Palette, ShieldCheck, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import BackLink from '@/components/common/BackLink.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as editAccount } from '@/routes/account';
import { edit as editAppearance } from '@/routes/appearance';
import { show as showProfile } from '@/routes/member-profile';
import { edit as editNotifications } from '@/routes/notification-preferences';
import { edit as editPartnerProfile } from '@/routes/partner/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { t } = useTranslations();
const page = usePage();
const isMember = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'user'),
);
const isPartner = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'partner'),
);
const profileDestination = computed(() => {
    if (isMember.value) {
        return showProfile();
    }

    return isPartner.value ? editPartnerProfile() : dashboard();
});
const sidebarNavItems: NavItem[] = [
    {
        title: t('account.settings.account'),
        icon: UserRound,
        href: editAccount(),
    },
    {
        title: t('account.settings.security'),
        icon: ShieldCheck,
        href: editSecurity(),
    },
    {
        title: t('account.settings.appearance'),
        icon: Palette,
        href: editAppearance(),
    },
    {
        title: t('account.settings.notifications.navigation'),
        icon: Bell,
        href: editNotifications(),
    },
];

const visibleNavItems = computed(() =>
    isMember.value
        ? sidebarNavItems
        : sidebarNavItems.filter(
              (item) => toUrl(item.href) === toUrl(editNotifications()),
          ),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <main
        class="mx-auto w-full max-w-2xl min-w-0 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-6 sm:px-6 sm:pt-6"
    >
        <header class="mb-6 space-y-4">
            <Button
                as-child
                variant="ghost"
                class="-ml-3 min-h-11 gap-2 rounded-xl px-3 text-muted-foreground"
            >
                <BackLink
                    :href="profileDestination"
                    data-test="settings-back-to-profile"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    {{ t('common.actions.back') }}
                </BackLink>
            </Button>
            <div class="space-y-1.5">
                <h1
                    class="text-[1.625rem] leading-8 font-semibold tracking-tight sm:text-[1.75rem]"
                >
                    {{ t('account.settings.title') }}
                </h1>
                <p class="max-w-prose text-sm leading-6 text-muted-foreground">
                    {{ t('account.settings.description') }}
                </p>
            </div>
        </header>
        <nav
            :class="isMember ? 'grid-cols-4' : 'grid-cols-1'"
            class="mb-5 grid gap-1 rounded-2xl border border-border/70 bg-card p-1.5"
            :aria-label="t('account.settings.navigation')"
        >
            <Link
                v-for="item in visibleNavItems"
                :key="toUrl(item.href)"
                :href="item.href"
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
                :class="[
                    'flex min-h-16 min-w-0 flex-col items-center justify-center gap-1.5 rounded-xl px-1 py-2 text-center text-xs font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:min-h-12 sm:flex-row sm:gap-2 sm:px-3 sm:text-sm',
                    isCurrentOrParentUrl(item.href)
                        ? 'bg-secondary text-secondary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
            >
                <component
                    :is="item.icon"
                    class="size-5 shrink-0 sm:size-4"
                    aria-hidden="true"
                />
                <span class="max-w-full wrap-anywhere">{{ item.title }}</span>
            </Link>
        </nav>
        <div class="min-w-0 space-y-4"><slot /></div>
    </main>
</template>
