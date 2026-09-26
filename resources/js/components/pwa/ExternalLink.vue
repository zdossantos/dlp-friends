<script setup lang="ts">
import { computed } from 'vue';
import { isSafeExternalUrl } from '@/lib/pwa/capabilities';

const props = defineProps<{ href: string }>();
const safeHref = computed(() => {
    const origin =
        typeof window === 'undefined'
            ? 'https://dlp-friends.test'
            : window.location.origin;

    return isSafeExternalUrl(props.href, origin) ? props.href : undefined;
});
</script>

<template>
    <a
        v-if="safeHref"
        :href="safeHref"
        target="_blank"
        rel="noopener noreferrer"
    >
        <slot />
    </a>
</template>
