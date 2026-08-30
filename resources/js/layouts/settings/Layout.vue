<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { edit as editAccount } from '@/routes/account';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { t } = useTranslations();
const sidebarNavItems: NavItem[] = [
    {
        title: t('account.settings.account'),
        href: editAccount(),
    },
    {
        title: t('account.settings.security'),
        href: editSecurity(),
    },
    {
        title: t('account.settings.appearance'),
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('account.settings.title')"
            :description="t('account.settings.description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full lg:w-48">
                <nav
                    class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible"
                    :aria-label="t('account.settings.navigation')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'shrink-0 justify-start lg:w-full',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
