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
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { restoreEventWorkspaceScroll } from '@/lib/eventWorkspaceScroll';

defineProps<{
    open: boolean;
    title: string;
    description: string;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const isMobile = useMediaQuery('(max-width: 639px)');

function updateFromDrawer(open: boolean): void {
    if (!open && !isMobile.value) {
        return;
    }

    emit('update:open', open);
}

function updateFromDialog(open: boolean): void {
    if (!open && isMobile.value) {
        return;
    }

    emit('update:open', open);
}

function focusPanelWithoutScrolling(event: Event): void {
    event.preventDefault();
    (event.currentTarget as HTMLElement | null)?.focus({ preventScroll: true });
    restoreEventWorkspaceScroll();
}
</script>

<template>
    <Drawer v-if="isMobile" :open="open" @update:open="updateFromDrawer">
        <DrawerContent
            data-test="event-panel"
            data-panel-mode="drawer"
            :initial-focus="false"
            tabindex="-1"
            @focusin="restoreEventWorkspaceScroll"
            @open-auto-focus="focusPanelWithoutScrolling"
            class="max-h-[92svh] rounded-t-3xl border-border bg-card px-0 pb-[max(1rem,env(safe-area-inset-bottom))] text-card-foreground"
        >
            <DrawerHeader class="shrink-0 px-5 text-left">
                <DrawerTitle>{{ title }}</DrawerTitle>
                <DrawerDescription>{{ description }}</DrawerDescription>
            </DrawerHeader>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-2">
                <slot />
            </div>
        </DrawerContent>
    </Drawer>

    <Dialog v-else :open="open" @update:open="updateFromDialog">
        <DialogContent
            data-test="event-panel"
            data-panel-mode="dialog"
            :show-close-button="false"
            tabindex="-1"
            @focusin="restoreEventWorkspaceScroll"
            @open-auto-focus="focusPanelWithoutScrolling"
            class="max-h-[min(90svh,52rem)] overflow-hidden border-border bg-card p-0 text-card-foreground sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0 px-6 pt-6">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <div class="min-h-0 overflow-y-auto px-6 pb-6">
                <slot />
            </div>
        </DialogContent>
    </Dialog>
</template>
