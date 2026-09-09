<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

defineProps<{
    open: boolean;
    title: string;
    description: string;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const isMobile = useMediaQuery('(max-width: 639px)');
</script>

<template>
    <Sheet
        v-if="isMobile"
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <SheetContent
            side="bottom"
            data-test="event-panel"
            data-panel-mode="sheet"
            class="max-h-[92svh] rounded-t-3xl border-border bg-card px-0 pb-[max(1rem,env(safe-area-inset-bottom))] text-card-foreground"
        >
            <SheetHeader class="shrink-0 px-5 pr-14 text-left">
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription>{{ description }}</SheetDescription>
            </SheetHeader>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-2">
                <slot />
            </div>
        </SheetContent>
    </Sheet>

    <Dialog v-else :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            data-test="event-panel"
            data-panel-mode="dialog"
            class="max-h-[min(90svh,52rem)] overflow-hidden border-border bg-card p-0 text-card-foreground sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0 px-6 pt-6 pr-14">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <div class="min-h-0 overflow-y-auto px-6 pb-6">
                <slot />
            </div>
        </DialogContent>
    </Dialog>
</template>
