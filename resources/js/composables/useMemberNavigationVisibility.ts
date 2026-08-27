import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

const memberNavigationPaths = new Set([
    '/discover',
    '/conversations',
    '/profile',
]);

export function useMemberNavigationVisibility(): ComputedRef<boolean> {
    const page = usePage();

    return computed(() =>
        memberNavigationPaths.has(page.url.split('?')[0] ?? ''),
    );
}
