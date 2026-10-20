<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(
    defineProps<{
        open?: boolean;
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
                        variant="outline"
                        :disabled="busy"
                        :data-test="`${testPrefix ?? 'confirm'}-cancel`"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </component>
                <Button
                    type="button"
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
