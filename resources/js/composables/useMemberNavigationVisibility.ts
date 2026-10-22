import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

const memberNavigationPaths = new Set([
    '/discover',
    '/discover/passed',
    '/events',
    '/visits',
    '/trips',
    '/conversations',
    '/notifications',
    '/profile',
]);
const memberNavigationPrefixes = [
    '/visits/',
    '/trips/',
    '/events/',
    '/partner/',
    '/settings/',
];

export function useMemberNavigationVisibility(): ComputedRef<boolean> {
    const page = usePage();

    return computed(() => {
        if (
            ['Conversations/Show', 'Conversations/Group'].includes(
                page.component,
            )
        ) {
            return false;
        }

        const path = new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname;

        return (
            memberNavigationPaths.has(path) ||
            memberNavigationPrefixes.some((prefix) => path.startsWith(prefix))
        );
    });
}
