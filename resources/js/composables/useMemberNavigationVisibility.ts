import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

const memberNavigationPaths = new Set([
    '/discover',
    '/conversations',
    '/profile',
]);
const memberNavigationPrefixes = ['/settings/'];

export function useMemberNavigationVisibility(): ComputedRef<boolean> {
    const page = usePage();

    return computed(() => {
        const path = page.url.split('?')[0] ?? '';

        return (
            memberNavigationPaths.has(path) ||
            memberNavigationPrefixes.some((prefix) => path.startsWith(prefix))
        );
    });
}
