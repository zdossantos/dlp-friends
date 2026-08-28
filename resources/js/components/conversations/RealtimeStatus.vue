<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';

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
        class="flex shrink-0 items-center justify-center gap-2 border-b bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
    >
        <template v-if="unavailable">
            <span>Temps réel indisponible. Vos messages restent affichés.</span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 px-2"
                @click="onRetry"
            >
                <RefreshCw class="size-4" aria-hidden="true" />
                Réessayer
            </Button>
        </template>
        <span v-else>Connexion au temps réel…</span>
    </div>
</template>
