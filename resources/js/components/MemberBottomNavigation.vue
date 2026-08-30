<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageCircle, Sparkles, UserRound } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import { useTranslations } from '@/composables/useTranslations';
import { index as conversations } from '@/routes/conversations';
import { index as discovery } from '@/routes/discovery';
import { show as showProfile } from '@/routes/member-profile';

const { isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useTranslations();

const shouldShow = useMemberNavigationVisibility();

const items = [
    { label: t('discovery.navigation'), href: discovery(), icon: Sparkles },
    {
        label: t('conversations.navigation'),
        href: conversations(),
        icon: MessageCircle,
        activeParents: ['/conversations'],
    },
    {
        label: t('profile.navigation'),
        href: showProfile(),
        icon: UserRound,
        activeParents: ['/settings'],
    },
];

function isActive(item: (typeof items)[number]): boolean {
    return (
        isCurrentOrParentUrl(item.href) ||
        item.activeParents?.some((parent) => isCurrentOrParentUrl(parent)) ===
            true
    );
}
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
                class="grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    isActive(item) ? 'bg-secondary text-primary' : undefined
                "
            >
                <component :is="item.icon" class="size-6" aria-hidden="true" />
            </Link>
        </nav>
    </div>
</template>
