<script setup lang="ts">
import { nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';

const props = withDefaults(
    defineProps<{
        open?: boolean;
        returnFocus?: string;
        testPrefix?: string;
        title: string;
        description: string;
        confirmLabel: string;
        destructive?: boolean;
        busy?: boolean;
    }>(),
    { open: undefined, destructive: false, busy: false },
);

const emit = defineEmits<{ confirm: []; 'update:open': [open: boolean] }>();
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();
function restoreFocus(event: Event) {
    if (!props.returnFocus) {
        return;
    }

    const element = document.querySelector<HTMLElement>(props.returnFocus);

    if (!element) {
        return;
    }

    event.preventDefault();
    nextTick(() =>
        requestAnimationFrame(() => element.focus({ preventScroll: true })),
    );
}
</script>

<template>
    <component
        :is="Modal.Root"
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <component v-if="$slots.default" :is="Modal.Trigger" as-child>
            <slot />
        </component>
        <component
            :is="Modal.Content"
            @close-auto-focus="restoreFocus"
            :data-test="`${testPrefix ?? 'confirm'}-dialog`"
            :class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"
        >
            <component :is="Modal.Header">
                <component :is="Modal.Title">{{ title }}</component>
                <component :is="Modal.Description">{{ description }}</component>
            </component>
            <component :is="Modal.Footer" class="gap-2">
                <component :is="Modal.Close" as-child>
                    <Button
                        type="button"
                        class="min-h-11"
                        variant="outline"
                        :disabled="busy"
                        :data-test="`${testPrefix ?? 'confirm'}-cancel`"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </component>
                <Button
                    type="button"
                    class="min-h-11"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="busy"
                    :data-test="`${testPrefix ?? 'confirm'}-submit`"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </component>
        </component>
    </component>
</template>
