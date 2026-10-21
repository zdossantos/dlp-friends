<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { goBack } from '@/lib/navigationHistory';
import { toUrl } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        href: NonNullable<InertiaLinkProps['href']>;
        preserveState?: boolean;
        preserveScroll?: boolean;
        only?: string[];
    }>(),
    { preserveScroll: true },
);

function back(event: MouseEvent): void {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        (event.currentTarget as HTMLAnchorElement)?.target === '_blank'
    ) {
        return;
    }

    event.preventDefault();
    goBack(toUrl(props.href), {
        preserveState: props.preserveState,
        preserveScroll: props.preserveScroll,
        only: props.only,
    });
}
</script>

<template>
    <a :href="toUrl(href)" @click="back"><slot /></a>
</template>
