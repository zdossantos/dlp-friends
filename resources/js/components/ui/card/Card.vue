<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import SeasonalDecorations from "@/components/seasonal/SeasonalDecorations.vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground relative isolate flex flex-col gap-6 overflow-hidden rounded-xl border py-6 shadow-sm [&>*:not([data-test^=seasonal-surface-])]:relative [&>*:not([data-test^=seasonal-surface-])]:z-10',
        props.class,
      )
    "
  >
    <slot />
    <SeasonalDecorations placement="card" />
  </div>
</template>
