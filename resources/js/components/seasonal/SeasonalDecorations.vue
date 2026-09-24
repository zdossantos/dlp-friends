<script setup lang="ts">
import {
    CandyCane,
    Ghost,
    Gift,
    MoonStar,
    Snowflake,
    Sparkles,
    TreePine,
} from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const activeTheme = computed(() => usePage().props.seasonalTheme.active);
</script>

<template>
    <div
        v-if="activeTheme === 'halloween'"
        data-test="seasonal-decoration-halloween"
        aria-hidden="true"
        focusable="false"
        class="seasonal-decoration seasonal-decoration-static pointer-events-none fixed top-16 right-3 z-0 h-32 w-56 text-primary opacity-20 sm:h-40 sm:w-72"
    >
        <MoonStar class="absolute top-0 right-2 size-14" :stroke-width="1.5" />
        <Ghost class="absolute right-20 bottom-0 size-16" :stroke-width="1.5" />
        <Sparkles class="absolute top-10 left-4 size-10" :stroke-width="1.5" />
    </div>

    <div
        v-else-if="activeTheme === 'christmas'"
        data-test="seasonal-decoration-christmas"
        aria-hidden="true"
        focusable="false"
        class="seasonal-decoration seasonal-decoration-static pointer-events-none fixed top-16 right-3 z-0 h-32 w-56 text-primary opacity-20 sm:h-40 sm:w-72"
    >
        <Snowflake class="absolute top-0 right-2 size-14" :stroke-width="1.5" />
        <TreePine
            class="absolute bottom-0 left-5 size-16"
            :stroke-width="1.5"
        />
        <Gift class="absolute right-20 bottom-1 size-12" :stroke-width="1.5" />
        <CandyCane class="absolute top-5 left-24 size-10" :stroke-width="1.5" />
    </div>
</template>
