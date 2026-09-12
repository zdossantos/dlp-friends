<script setup lang="ts">
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { restoreEventWorkspaceScroll } from '@/lib/eventWorkspaceScroll';

defineProps<{
    open: boolean;
    title: string;
    description: string;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { isDesktop, Modal } = useResponsiveModal();

function updateOpen(open: boolean): void {
    emit('update:open', open);
}

function focusPanelWithoutScrolling(event: Event): void {
    event.preventDefault();
    (event.currentTarget as HTMLElement | null)?.focus({ preventScroll: true });
    restoreEventWorkspaceScroll();
}
</script>

<template>
    <component :is="Modal.Root" :open="open" @update:open="updateOpen">
        <component
            :is="Modal.Content"
            data-test="event-panel"
            :data-panel-mode="isDesktop ? 'dialog' : 'drawer'"
            :initial-focus="false"
            v-bind="isDesktop ? { showCloseButton: false } : {}"
            tabindex="-1"
            @focusin="restoreEventWorkspaceScroll"
            @open-auto-focus="focusPanelWithoutScrolling"
            :class="
                isDesktop
                    ? 'max-h-[min(90svh,52rem)] overflow-hidden border-border bg-card p-0 text-card-foreground sm:max-w-2xl'
                    : 'max-h-[92svh] rounded-t-3xl border-border bg-card px-0 pb-[max(1rem,env(safe-area-inset-bottom))] text-card-foreground'
            "
        >
            <component
                :is="Modal.Header"
                :class="
                    isDesktop ? 'shrink-0 px-6 pt-6' : 'shrink-0 px-5 text-left'
                "
            >
                <component :is="Modal.Title">{{ title }}</component>
                <component :is="Modal.Description">{{ description }}</component>
            </component>
            <div
                :class="
                    isDesktop
                        ? 'min-h-0 overflow-y-auto px-6 pb-6'
                        : 'min-h-0 flex-1 overflow-y-auto px-5 pb-2'
                "
            >
                <slot />
            </div>
        </component>
    </component>
</template>
