<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    accessibleName?: string;
    className?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <span
        :aria-hidden="props.accessibleName ? undefined : 'true'"
        :aria-label="props.accessibleName"
        data-test="app-logo-icon"
        :role="props.accessibleName ? 'img' : undefined"
        :class="className"
        class="inline-block shrink-0 bg-[url('/brand/dlp-friends-logo.svg')] bg-contain bg-center bg-no-repeat dark:bg-[url('/brand/dlp-friends-logo-dark.svg')]"
        v-bind="$attrs"
    />
</template>
