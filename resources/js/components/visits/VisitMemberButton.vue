<script setup lang="ts">
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { VisitMember } from '@/types/visit';

defineProps<{ member: VisitMember; emphasized?: boolean }>();
const emit = defineEmits<{ select: [id: number] }>();
const { t } = useTranslations();
</script>

<template>
    <button
        type="button"
        class="flex min-h-14 w-full items-center gap-3 rounded-lg py-2 text-left focus-visible:ring-2 focus-visible:ring-ring"
        @click="emit('select', member.id)"
    >
        <UserAvatar
            :avatar="member.avatar"
            :display-name="member.display_name"
        />
        <span
            class="min-w-0 flex-1 truncate"
            :class="{ 'font-medium': emphasized }"
            >{{ member.display_name }}</span
        >
        <span v-if="member.is_match" class="text-xs text-muted-foreground">{{
            t('visits.match')
        }}</span>
    </button>
</template>
