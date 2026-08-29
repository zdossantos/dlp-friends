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
        class="inline-block shrink-0 bg-current [mask-image:url('/brand/dlp-friends-logo.svg')] [mask-size:contain] [mask-position:center] [mask-repeat:no-repeat] [-webkit-mask-image:url('/brand/dlp-friends-logo.svg')] [-webkit-mask-position:center] [-webkit-mask-repeat:no-repeat] [-webkit-mask-size:contain]"
        v-bind="$attrs"
    />
</template>
