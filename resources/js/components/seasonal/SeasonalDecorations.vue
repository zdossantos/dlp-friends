<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const activeTheme = computed(() => usePage().props.seasonalTheme.active);
</script>

<template>
    <svg
        v-if="activeTheme === 'halloween'"
        data-test="seasonal-decoration-halloween"
        aria-hidden="true"
        focusable="false"
        viewBox="0 0 320 180"
        class="seasonal-decoration seasonal-decoration-static pointer-events-none fixed top-12 right-0 z-0 h-36 w-64 text-primary opacity-20 sm:h-44 sm:w-80"
    >
        <path
            fill="currentColor"
            fill-opacity=".34"
            d="M244 20a42 42 0 1 0 34 66 35 35 0 1 1-34-66Z"
        />
        <path
            fill="currentColor"
            d="m154 55 16-12 18 11 18-5-9 15 12 13-20-3-18 12-16-15-20 3 11-13-8-15 16 9Z"
        />
        <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="4"
            d="M79 43c18 11 22 26 14 44M80 44c-14 12-22 26-22 43m22-43c2 17-2 31-12 42m12-42c13 15 18 28 15 42"
        />
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M219 111c-19 0-35 13-35 29 0 14 13 25 30 28l5-12 5 12c18-2 31-14 31-28 0-16-16-29-36-29Zm-14 24 9-6-2 12-7-6Zm29-6 9 6-7 6-2-12Zm-27 21h25l-13 8-12-8Z"
            clip-rule="evenodd"
        />
    </svg>

    <svg
        v-else-if="activeTheme === 'christmas'"
        data-test="seasonal-decoration-christmas"
        aria-hidden="true"
        focusable="false"
        viewBox="0 0 320 180"
        class="seasonal-decoration seasonal-decoration-static pointer-events-none fixed top-12 right-0 z-0 h-36 w-64 text-primary opacity-20 sm:h-44 sm:w-80"
    >
        <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="4"
            d="M247 23v58m-25-44 50 30m0-30-50 30m25-44-7 10m7-10 7 10m-32 4 12 1m-12-1 5-11m45 11-12 1m12-1-5-11m-45 41 12-1m-12 1 5 11m45-11-12-1m12 1-5 11"
        />
        <path
            fill="currentColor"
            d="m151 31 6 17 18 1-14 11 5 18-15-10-15 10 5-18-14-11 18-1 6-17Z"
        />
        <path
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="5"
            d="M45 145c34-27 64-45 105-54m-76 32-22-8m39-4-11-22m37 10 4-23m9 20 18 13"
        />
        <path
            fill="currentColor"
            fill-opacity=".7"
            d="M231 108c17 0 30 13 30 29s-13 29-30 29-30-13-30-29 13-29 30-29Zm0-13 11 14h-22l11-14Z"
        />
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="3"
            d="M216 132c10 8 20 8 30 0m-27 13c8 6 16 6 24 0"
        />
    </svg>
</template>
