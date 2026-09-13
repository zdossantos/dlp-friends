<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { restoreEventWorkspaceScroll } from '@/lib/eventWorkspaceScroll';

const props = defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    a11yHeading?: string | null;
    a11ySummary?: string | null;
    backHref?: string | null;
    backLabel?: string;
    backDataTest?: string;
    fullBleed?: boolean;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { isDesktop, Modal } = useResponsiveModal();
const hasVisibleHeader = computed(
    () => Boolean(props.title) || Boolean(props.description),
);

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
            :class="[
                isDesktop
                    ? 'max-h-[min(90svh,52rem)] overflow-hidden border-border bg-card p-0 text-card-foreground sm:max-w-2xl'
                    : 'max-h-[92svh] overflow-hidden rounded-t-3xl border-border bg-card px-0 pb-[max(1rem,env(safe-area-inset-bottom))] text-card-foreground',
                !isDesktop && !hasVisibleHeader
                    ? '[&>[data-slot=drawer-handle]]:absolute [&>[data-slot=drawer-handle]]:top-0 [&>[data-slot=drawer-handle]]:left-1/2 [&>[data-slot=drawer-handle]]:z-50 [&>[data-slot=drawer-handle]]:-translate-x-1/2'
                    : '',
            ]"
        >
            <component
                :is="hasVisibleHeader ? Modal.Header : 'div'"
                :data-test="hasVisibleHeader ? 'event-panel-header' : undefined"
                :class="[
                    hasVisibleHeader
                        ? isDesktop
                            ? 'sticky top-0 z-20 shrink-0 bg-card px-6 pt-6 pb-4'
                            : 'sticky top-0 z-20 shrink-0 bg-card px-5 pb-4 text-left'
                        : isDesktop
                          ? 'sr-only'
                          : 'pointer-events-none absolute top-5 left-5 z-40',
                ]"
            >
                <div :class="hasVisibleHeader ? 'flex items-start gap-3' : ''">
                    <Button
                        v-if="backHref && !isDesktop"
                        as-child
                        type="button"
                        variant="outline"
                        size="icon"
                        class="pointer-events-auto size-11 shrink-0 rounded-full bg-card/90 shadow-sm backdrop-blur"
                    >
                        <Link
                            :href="backHref"
                            preserve-scroll
                            :data-test="backDataTest"
                            :aria-label="backLabel"
                        >
                            <ArrowLeft class="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                    <div v-if="hasVisibleHeader" class="min-w-0 flex-1">
                        <component :is="Modal.Title">{{ title }}</component>
                        <component :is="Modal.Description">{{
                            description
                        }}</component>
                    </div>
                    <div v-else class="sr-only">
                        <component :is="Modal.Title">{{
                            a11yHeading
                        }}</component>
                        <component :is="Modal.Description">{{
                            a11ySummary
                        }}</component>
                    </div>
                </div>
            </component>
            <div
                data-test="event-panel-scroll"
                :class="
                    fullBleed
                        ? 'min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto bg-card'
                        : isDesktop
                          ? 'min-h-0 min-w-0 overflow-x-hidden overflow-y-auto px-6 pb-6'
                          : 'min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-5 pb-2'
                "
            >
                <slot />
            </div>
            <div
                v-if="isDesktop && backHref"
                class="shrink-0 border-t border-border bg-card px-6 py-4"
            >
                <Button as-child type="button" variant="outline">
                    <Link
                        :href="backHref"
                        preserve-scroll
                        :data-test="backDataTest"
                        :aria-label="backLabel"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        {{ backLabel }}
                    </Link>
                </Button>
            </div>
        </component>
    </component>
</template>
