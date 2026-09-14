<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { AvatarOption } from '@/types';

const props = defineProps<{
    avatar: AvatarOption | null;
    displayName: string | null;
}>();

const background = computed(() =>
    props.avatar
        ? {
              backgroundImage: `linear-gradient(135deg, ${props.avatar.primary_color}, ${props.avatar.secondary_color})`,
          }
        : undefined,
);

const fallback = computed(
    () => props.displayName?.trim().slice(0, 1).toUpperCase() || '?',
);
</script>

<template>
    <Avatar :style="background">
        <AvatarImage
            v-if="avatar"
            :src="avatar.image_url"
            :alt="displayName ?? avatar.name"
            class="object-contain"
        />
        <AvatarFallback class="bg-transparent text-foreground">
            {{ fallback }}
        </AvatarFallback>
    </Avatar>
</template>
