<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import type { SliderRootEmits, SliderRootProps } from 'reka-ui';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<SliderRootProps & {
    class?: HTMLAttributes['class'];
    thumbLabels?: string[];
}>();
const emits = defineEmits<SliderRootEmits>();
const delegatedProps = reactiveOmit(props, 'class', 'thumbLabels');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <SliderRoot
        v-slot="{ modelValue }"
        data-slot="slider"
        v-bind="forwarded"
        :class="cn('relative flex min-h-11 w-full touch-none items-center select-none data-[disabled]:opacity-50 data-[orientation=vertical]:h-full data-[orientation=vertical]:min-h-44 data-[orientation=vertical]:w-auto data-[orientation=vertical]:flex-col', props.class)"
    >
        <SliderTrack data-slot="slider-track" class="relative grow overflow-hidden rounded-full bg-muted data-[orientation=horizontal]:h-1.5 data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-1.5">
            <SliderRange data-slot="slider-range" class="absolute bg-primary data-[orientation=horizontal]:h-full data-[orientation=vertical]:w-full" />
        </SliderTrack>
        <SliderThumb
            v-for="(_, index) in modelValue"
            :key="index"
            data-slot="slider-thumb"
            :aria-label="thumbLabels?.[index]"
            class="block size-5 shrink-0 rounded-full border-2 border-primary bg-background shadow-sm ring-ring/50 transition-shadow before:absolute before:inset-1/2 before:size-11 before:-translate-1/2 hover:ring-4 focus-visible:ring-4 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
        />
    </SliderRoot>
</template>
