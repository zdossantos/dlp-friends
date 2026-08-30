<script lang="ts" setup>
import type { ToasterProps } from 'vue-sonner';
import {
    CircleCheckIcon,
    InfoIcon,
    Loader2Icon,
    OctagonXIcon,
    TriangleAlertIcon,
    XIcon,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import { Toaster as Sonner } from 'vue-sonner';
import { resolveToasterPosition } from '@/components/ui/sonner/position';
import { cn } from '@/lib/utils';

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>();
const isMobile = useMediaQuery('(max-width: 639px)');
const position = computed(() =>
    resolveToasterPosition(isMobile.value, props.position),
);
const mobileOffset = computed(
    () =>
        props.mobileOffset ?? {
            top: '1rem',
            right: '1rem',
            bottom: '1rem',
            left: '1rem',
        },
);
</script>

<template>
  <Sonner
    v-bind="props"
    :class="cn('toaster group', props.class)"
    :position="position"
    :mobile-offset="mobileOffset"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
    }"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>

<style>
[data-sonner-toast] {
    pointer-events: none;
}
</style>
