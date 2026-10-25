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
        class="flex h-[4.25rem] w-32 items-end rounded-lg border border-white/60 p-2 shadow-md"
        :style="{ backgroundColor: palette.background, color: palette.ink }"
    >
        <svg
            class="absolute inset-0 size-full"
            viewBox="0 0 128 68"
            fill="none"
            aria-hidden="true"
        >
            <rect
                x="4"
                y="4"
                width="120"
                height="60"
                rx="5"
                stroke="currentColor"
                opacity=".25"
            />
            <path
                d="M70 0C82 12 96 24 128 26M83 0C93 10 109 16 128 17"
                stroke="currentColor"
                opacity=".2"
            />
            <circle cx="16" cy="16" r="4" stroke="currentColor" />
            <path d="M23 16H38" stroke="currentColor" stroke-linecap="round" />
        </svg>
        <span class="relative text-xs leading-tight font-semibold">{{
            t(`profile.pass.tags.${pass}`)
        }}</span>
    </div>
</template>
