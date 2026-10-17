<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { t } = useTranslations();

defineOptions({
    inheritAttrs: false,
});

type Props = {
    accessibleName?: string;
    className?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <span
        :aria-hidden="props.accessibleName ? undefined : 'true'"
        :aria-label="props.accessibleName"
        data-test="app-logo-icon"
        :role="props.accessibleName ? 'img' : undefined"
        :class="className"
        class="relative inline-block shrink-0 bg-[url('/brand/dlp-friends-logo.svg')] bg-contain bg-center bg-no-repeat dark:bg-[url('/brand/dlp-friends-logo-dark.svg')] [.seasonal-halloween_&]:bg-[url('/brand/dlp-friends-logo-halloween.svg')]"
        :style="
            page.props.environment.isBeta
                ? { containerType: 'inline-size' }
                : undefined
        "
        v-bind="$attrs"
    >
        <span
            v-if="page.props.environment.isBeta"
            aria-hidden="true"
            data-test="beta-badge"
            class="absolute right-0 bottom-0 rounded-xs border border-[#18121f] bg-white px-[4cqw] py-[1cqw] text-[18cqw] leading-none font-bold text-[#18121f]"
            >{{ t('brand.beta_badge') }}</span
        >
    </span>
</template>
