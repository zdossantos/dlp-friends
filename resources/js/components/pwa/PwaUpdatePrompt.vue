<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePwa } from '@/composables/usePwa';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { updateAvailable, activateWaitingWorker } = usePwa();
</script>

<template>
    <aside
        v-if="updateAvailable"
        class="pwa-safe-toast fixed inset-x-4 bottom-4 z-[70] mx-auto flex max-w-lg items-center gap-3 rounded-2xl border bg-card p-4 text-card-foreground shadow-xl"
        role="status"
        aria-live="polite"
    >
        <RefreshCw class="size-5 shrink-0 text-primary" aria-hidden="true" />
        <div class="min-w-0 flex-1">
            <p class="font-medium">{{ t('common.pwa.update_title') }}</p>
            <p class="text-sm text-muted-foreground">
                {{ t('common.pwa.update_description') }}
            </p>
        </div>
        <Button class="min-h-11" type="button" @click="activateWaitingWorker">
            {{ t('common.pwa.update_action') }}
        </Button>
    </aside>
</template>
