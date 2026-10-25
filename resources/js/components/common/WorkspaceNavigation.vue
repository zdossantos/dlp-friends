<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    label: string;
    items: { href: string; label: string; active: boolean; testId: string }[];
}>();
</script>

<template>
    <nav class="grid grid-cols-2 rounded-xl bg-muted p-1" :aria-label="label">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :data-test="item.testId"
            :aria-current="item.active ? 'page' : undefined"
            class="min-h-11 rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                item.active
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
