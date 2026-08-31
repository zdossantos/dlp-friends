<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

defineProps<{
    unavailable: boolean;
    reconnecting: boolean;
    onRetry: () => void;
}>();
</script>

<template>
    <div
        v-if="unavailable || reconnecting"
        role="status"
        class="flex shrink-0 items-center justify-center gap-2 border-b bg-secondary px-4 py-2 text-sm text-secondary-foreground"
    >
        <template v-if="unavailable">
            <span>{{ t('conversations.realtime.unavailable') }}</span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 px-2"
                @click="onRetry"
            >
                <RefreshCw class="size-4" aria-hidden="true" />
                {{ t('conversations.realtime.retry') }}
            </Button>
        </template>
        <span v-else>{{ t('conversations.realtime.connecting') }}</span>
    </div>
</template>
