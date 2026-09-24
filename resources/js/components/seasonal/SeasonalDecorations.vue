<script setup lang="ts">
import {
    Candy,
    CandyCane,
    Ghost,
    Gift,
    MoonStar,
    Snowflake,
    Sparkles,
    TreePine,
    type LucideIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import { useSeasonalTheme } from '@/composables/useSeasonalTheme';
import type { SeasonalThemeName } from '@/types/seasonalTheme';

type Placement = 'global' | 'card' | 'panel' | 'hero';
type Decoration = { icon: LucideIcon; class: string };

const props = withDefaults(defineProps<{ placement?: Placement }>(), {
    placement: 'global',
});

const decorationSets: Record<
    Exclude<SeasonalThemeName, null>,
    Record<Placement, Decoration[]>
> = {
    halloween: {
        global: [
            { icon: MoonStar, class: 'absolute top-[12%] left-[5%] size-10' },
            { icon: Ghost, class: 'absolute right-[3%] bottom-[20%] size-14' },
            { icon: Sparkles, class: 'absolute top-[42%] right-[4%] size-8' },
        ],
        card: [
            {
                icon: Ghost,
                class: 'absolute -right-3 -bottom-3 size-20 rotate-6 opacity-[0.09]',
            },
            {
                icon: Candy,
                class: 'absolute right-[28%] bottom-4 size-6 -rotate-12 opacity-[0.12]',
            },
        ],
        panel: [
            {
                icon: Ghost,
                class: 'absolute -right-3 -bottom-3 size-20 rotate-6 opacity-[0.09]',
            },
            {
                icon: Candy,
                class: 'absolute right-[28%] bottom-4 size-6 -rotate-12 opacity-[0.12]',
            },
            {
                icon: MoonStar,
                class: 'absolute top-5 left-5 size-8 -rotate-6 opacity-[0.11]',
            },
        ],
        hero: [
            {
                icon: Ghost,
                class: 'absolute right-4 bottom-3 size-20 rotate-6 opacity-[0.12]',
            },
            {
                icon: Candy,
                class: 'absolute top-6 left-[12%] size-7 -rotate-12 opacity-[0.16]',
            },
            {
                icon: MoonStar,
                class: 'absolute top-5 right-[12%] size-9 rotate-6 opacity-[0.14]',
            },
        ],
    },
    christmas: {
        global: [
            { icon: Snowflake, class: 'absolute top-[12%] left-[5%] size-10' },
            {
                icon: Gift,
                class: 'absolute right-[6%] bottom-[17%] size-11 -rotate-6',
            },
            {
                icon: Gift,
                class: 'absolute top-[38%] left-[3%] size-7 rotate-12 opacity-70',
            },
            {
                icon: CandyCane,
                class: 'absolute right-[4%] bottom-[38%] size-8 rotate-6',
            },
        ],
        card: [
            {
                icon: TreePine,
                class: 'absolute -right-3 -bottom-4 size-24 rotate-3 opacity-[0.09]',
            },
            {
                icon: Gift,
                class: 'absolute right-[29%] bottom-4 size-6 -rotate-6 opacity-[0.13]',
            },
        ],
        panel: [
            {
                icon: TreePine,
                class: 'absolute -right-3 -bottom-4 size-24 rotate-3 opacity-[0.09]',
            },
            {
                icon: Gift,
                class: 'absolute right-[29%] bottom-4 size-6 -rotate-6 opacity-[0.13]',
            },
            {
                icon: Snowflake,
                class: 'absolute top-5 left-5 size-8 rotate-12 opacity-[0.12]',
            },
        ],
        hero: [
            {
                icon: TreePine,
                class: 'absolute right-3 bottom-1 size-24 rotate-3 opacity-[0.13]',
            },
            {
                icon: Gift,
                class: 'absolute top-7 left-[12%] size-8 -rotate-6 opacity-[0.17]',
            },
            {
                icon: Snowflake,
                class: 'absolute top-5 right-[14%] size-9 rotate-12 opacity-[0.16]',
            },
        ],
    },
};

const seasonalTheme = useSeasonalTheme();
const activeTheme = computed(() => seasonalTheme.value.active);
const decorations = computed(() =>
    activeTheme.value === null
        ? []
        : decorationSets[activeTheme.value][props.placement],
);
const testId = computed(() =>
    props.placement === 'global'
        ? `seasonal-decoration-${activeTheme.value}`
        : `seasonal-surface-${activeTheme.value}`,
);
</script>

<template>
    <div
        v-if="activeTheme !== null"
        :data-test="testId"
        :data-seasonal-placement="placement"
        aria-hidden="true"
        focusable="false"
        :class="[
            'seasonal-decoration seasonal-decoration-static pointer-events-none inset-0 z-0 overflow-hidden text-primary',
            placement === 'global'
                ? 'fixed opacity-15'
                : 'absolute opacity-100',
        ]"
    >
        <component
            :is="decoration.icon"
            v-for="(decoration, index) in decorations"
            :key="index"
            :class="decoration.class"
            :stroke-width="1.5"
        />
    </div>
</template>
