<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { DisneylandPass } from '@/types';

const props = defineProps<{ pass: DisneylandPass }>();
const { t } = useTranslations();
const palette = computed(() => {
    if (props.pass === 'gold') {
        return { background: '#f3d584', ink: '#503b10' };
    }

    if (props.pass === 'silver') {
        return { background: '#e2e8ee', ink: '#344454' };
    }

    return { background: '#e7ba96', ink: '#57351f' };
});
</script>

<template>
    <div
        data-test="profile-pass-card"
        :title="t('profile.pass.self_declared')"
        class="flex h-13 w-24 items-end overflow-hidden rounded-lg border border-white/60 p-1.5 shadow-md"
        :style="{ backgroundColor: palette.background, color: palette.ink }"
    >
        <span
            class="absolute inset-0.5 rounded-[calc(var(--radius)-3px)] border border-current opacity-25"
            aria-hidden="true"
        />
        <svg
            class="absolute inset-0 size-full"
            viewBox="0 0 128 68"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M70 0C82 12 96 24 128 26M83 0C93 10 109 16 128 17"
                stroke="currentColor"
                opacity=".2"
            />
            <circle cx="16" cy="16" r="4" stroke="currentColor" />
            <path d="M23 16H38" stroke="currentColor" stroke-linecap="round" />
        </svg>
        <span class="relative text-[0.65rem] leading-3 font-semibold">{{
            t(`profile.pass.tags.${pass}`)
        }}</span>
    </div>
</template>
