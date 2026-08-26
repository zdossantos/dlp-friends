<script setup lang="ts">
import { computed } from 'vue';
import type { AvatarOption } from '@/types';

const props = defineProps<{ avatar: AvatarOption }>();

const gradient = computed(() => ({
    backgroundImage: `linear-gradient(135deg, ${props.avatar.primary_color}, ${props.avatar.secondary_color})`,
}));
</script>

<template>
    <div
        class="flex aspect-square items-end justify-center overflow-hidden rounded-3xl"
        :style="gradient"
    >
        <img
            :src="avatar.image_url"
            :alt="`Avatar ${avatar.name}`"
            draggable="false"
            class="pointer-events-none h-full w-full object-contain select-none"
        />
    </div>
</template>
