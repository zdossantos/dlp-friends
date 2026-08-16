<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <circle cx="16" cy="15" r="6" fill="currentColor" />
        <circle cx="32" cy="15" r="6" fill="currentColor" opacity=".72" />
        <path
            fill="currentColor"
            d="M24 43C13.5 35.7 8 30.1 8 23.7 8 18.9 11.7 16 16 16c3.1 0 6 1.8 8 4.4 2-2.6 4.9-4.4 8-4.4 4.3 0 8 2.9 8 7.7 0 6.4-5.5 12-16 19.3Z"
        />
    </svg>
</template>
