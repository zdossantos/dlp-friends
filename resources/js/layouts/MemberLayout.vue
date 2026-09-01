<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import MemberBottomNavigation from '@/components/MemberBottomNavigation.vue';
import { Toaster } from '@/components/ui/sonner';
import { useMemberNavigationVisibility } from '@/composables/useMemberNavigationVisibility';
import { useTranslations } from '@/composables/useTranslations';

const reservesMemberNavigation = useMemberNavigationVisibility();
const legal = usePage().props.legal;
const { t } = useTranslations();
</script>

<template>
    <div
        class="relative flex h-svh w-full flex-col overflow-hidden bg-background text-foreground"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_42%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_38%)] opacity-35"
        />
        <div
            data-test="member-shell-content"
            class="relative flex min-h-0 w-full flex-1 flex-col overflow-y-auto overscroll-contain"
            :class="
                reservesMemberNavigation
                    ? '[padding-bottom:calc(5.5rem+env(safe-area-inset-bottom))]'
                    : undefined
            "
        >
            <slot />
            <footer
                class="mt-auto flex justify-center gap-4 px-4 py-6 text-xs text-muted-foreground"
            >
                <a
                    :href="legal.terms_url"
                    class="underline underline-offset-4"
                    >{{ t('common.legal.terms') }}</a
                >
                <a
                    :href="legal.privacy_url"
                    class="underline underline-offset-4"
                    >{{ t('common.legal.privacy') }}</a
                >
            </footer>
        </div>
        <MemberBottomNavigation />
        <Toaster />
    </div>
</template>
