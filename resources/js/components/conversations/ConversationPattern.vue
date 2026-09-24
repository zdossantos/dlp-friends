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

const rotations = [-34, 17, -9, 42, -21, 7, 29, -46, 13, -27, 38, -14];
const sizes = [14, 18, 22, 16, 25, 19, 15, 27, 20, 17];
const opacities = [0.52, 0.78, 0.61, 0.9, 0.68, 0.46, 0.84];

const decorations = computed(() => {
    const phase =
        variant.value === 'halloween'
            ? 11
            : variant.value === 'christmas'
              ? 23
              : 3;

    return Array.from({ length: 52 }, (_, index) => {
        const row = Math.floor(index / 7);
        const column = index % 7;
        const horizontalJitter = ((index * 37 + phase * 5) % 17) - 8;
        const verticalJitter = ((index * 19 + phase * 3) % 13) - 6;

        return {
            component:
                icons.value[(index * 3 + row + phase) % icons.value.length],
            x: column * 16.7 - 1.5 + horizontalJitter * 0.72,
            y: row * 14.4 - 1.2 + verticalJitter * 0.58,
            rotation: rotations[(index + phase + row * 2) % rotations.length],
            size: sizes[(index * 2 + phase + column) % sizes.length],
            opacity: opacities[(index + phase + column * 2) % opacities.length],
        };
    });
});
</script>

<template>
    <div
        data-test="conversation-pattern"
        :data-pattern="variant"
        aria-hidden="true"
        class="conversation-pattern pointer-events-none absolute inset-0 overflow-hidden"
    >
        <component
            :is="decoration.component"
            v-for="(decoration, index) in decorations"
            :key="`${variant}-${index}`"
            class="absolute"
            :stroke-width="1.25"
            :style="{
                left: `${decoration.x}%`,
                top: `${decoration.y}%`,
                width: `${decoration.size}px`,
                height: `${decoration.size}px`,
                opacity: decoration.opacity,
                transform: `translate(-50%, -50%) rotate(${decoration.rotation}deg)`,
            }"
        />
    </div>
</template>
