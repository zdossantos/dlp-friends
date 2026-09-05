<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { destroy } from '@/routes/admin/members';

const props = defineProps<{ memberId: number; displayName: string }>();
const { t } = useTranslations();
const open = ref(false);
const processing = ref(false);

function confirmDeletion(): void {
    router.delete(destroy(props.memberId).url, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                size="sm"
                variant="destructive"
                data-test="delete-member-trigger"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ t('administration.members.delete') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    t('administration.members.delete_title')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        t('administration.members.delete_description', {
                            name: displayName,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    data-test="confirm-delete-member"
                    :disabled="processing"
                    @click="confirmDeletion"
                >
                    {{
                        processing
                            ? t('administration.members.deleting')
                            : t('administration.members.confirm_delete')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
