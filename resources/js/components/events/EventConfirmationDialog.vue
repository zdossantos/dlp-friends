<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel: string;
        destructive?: boolean;
        busy?: boolean;
    }>(),
    { destructive: false, busy: false },
);

const emit = defineEmits<{ confirm: [] }>();
const { t } = useTranslations();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent data-test="event-confirm-dialog">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        data-test="event-confirm-cancel"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="busy"
                    data-test="event-confirm-submit"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
