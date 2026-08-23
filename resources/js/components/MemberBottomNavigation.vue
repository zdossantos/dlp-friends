<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sparkles, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as discovery } from '@/routes/discovery';
import { show as showProfile } from '@/routes/member-profile';

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const isProfileComplete = computed(() =>
    Boolean(page.props.auth.user.profile?.onboarding_completed_at),
);

const items = [
    { label: 'Découvrir', href: discovery(), icon: Sparkles },
    { label: 'Profil', href: showProfile(), icon: UserRound },
];
</script>

<template>
    <div
        v-if="isProfileComplete"
        class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 [padding-bottom:max(0.75rem,env(safe-area-inset-bottom))]"
    >
        <nav
            data-test="member-bottom-navigation"
            aria-label="Navigation principale"
            class="flex min-h-16 w-full max-w-sm items-center justify-around rounded-3xl border border-border/80 bg-card/95 px-4 shadow-xl shadow-primary/10 backdrop-blur"
        >
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                :aria-label="item.label"
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
                class="grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'bg-secondary text-primary'
                        : undefined
                "
            >
                <component :is="item.icon" class="size-6" aria-hidden="true" />
            </Link>
        </nav>
    </div>
</template>
