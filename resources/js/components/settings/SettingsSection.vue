<script setup lang="ts">
defineProps<{ title?: string; description?: string; headingId?: string }>();
</script>

<template>
    <section
        class="settings-section min-w-0 space-y-5 rounded-2xl border border-border/70 bg-card p-4 sm:p-6"
        :aria-labelledby="headingId"
    >
        <header v-if="title" class="space-y-1.5">
            <h2 :id="headingId" class="text-base leading-6 font-semibold">
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="text-sm leading-6 text-muted-foreground"
            >
                {{ description }}
            </p>
        </header>
        <slot />
    </section>
</template>

<style scoped>
@reference "../../../css/app.css";
.settings-section :deep([data-slot='button']) {
    @apply min-h-11 max-w-full rounded-xl whitespace-normal;
}
.settings-section :deep([data-slot='input']) {
    @apply min-h-11 rounded-xl;
}
</style>
