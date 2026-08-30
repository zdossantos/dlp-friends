<script lang="ts" setup>
import type { HTMLAttributes } from "vue"
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <nav
    :aria-label="t('common.accessibility.breadcrumb')"
    data-slot="breadcrumb"
    :class="props.class"
  >
    <slot />
  </nav>
</template>
