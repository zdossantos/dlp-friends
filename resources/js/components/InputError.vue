<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div aria-live="polite" :class="{ 'sr-only': !message }">
        <p v-if="message" class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
