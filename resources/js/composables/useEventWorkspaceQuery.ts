import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { EventFilters, EventWorkspaceContext } from '@/types/event';

export function useEventWorkspaceQuery(context: () => EventWorkspaceContext) {
    const page = usePage<{ filters?: EventFilters }>();

    return computed(() => ({
        ...page.props.filters,
        ...(context() === 'discover' ? {} : { origin: context() }),
    }));
}
