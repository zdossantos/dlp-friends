<script setup lang="ts">
import {
    Gift,
    Ghost,
    Hand,
    Leaf,
    Mail,
    MessageCircle,
    MoonStar,
    Snowflake,
    Sparkles,
    TreePine,
} from '@lucide/vue';
import { computed } from 'vue';
import { useSeasonalTheme } from '@/composables/useSeasonalTheme';

const seasonalTheme = useSeasonalTheme();
const variant = computed(() => seasonalTheme.value.active ?? 'standard');
const icons = computed(() => {
    if (variant.value === 'halloween') {
        return [MoonStar, Ghost, Leaf, Sparkles];
    }

    if (variant.value === 'christmas') {
        return [Snowflake, TreePine, Gift, Sparkles];
    }

    return [MessageCircle, Mail, Hand, Sparkles];
});
</script>

<template>
    <div
        data-test="conversation-pattern"
        :data-pattern="variant"
        aria-hidden="true"
        class="conversation-pattern pointer-events-none absolute inset-0 grid grid-cols-4 content-start gap-x-10 gap-y-14 overflow-hidden p-6"
    >
        <component
            :is="icons[(index - 1) % icons.length]"
            v-for="index in 24"
            :key="index"
            class="size-5"
            :class="index % 3 === 0 ? 'rotate-12' : '-rotate-6'"
            :stroke-width="1.25"
        />
    </div>
</template>
