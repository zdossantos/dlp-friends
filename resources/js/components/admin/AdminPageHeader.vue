<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header
        class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <h1
                class="text-2xl font-semibold tracking-tight break-words sm:text-3xl"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="max-w-3xl text-sm text-muted-foreground sm:text-base"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
