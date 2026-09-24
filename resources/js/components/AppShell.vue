<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import SeasonalDecorations from '@/components/seasonal/SeasonalDecorations.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <SeasonalDecorations />
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <SeasonalDecorations />
        <slot />
    </SidebarProvider>
</template>
