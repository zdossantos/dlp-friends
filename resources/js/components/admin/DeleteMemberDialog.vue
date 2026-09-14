<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import { destroy } from '@/routes/admin/members';

const props = defineProps<{ memberId: number; displayName: string }>();
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();
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
    <component :is="Modal.Root" v-model:open="open">
        <component :is="Modal.Trigger" as-child>
            <Button
                type="button"
                size="sm"
                variant="destructive"
                data-test="delete-member-trigger"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ t('administration.members.delete') }}
            </Button>
        </component>
        <component
            :is="Modal.Content"
            :class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"
        >
            <component :is="Modal.Header">
                <component :is="Modal.Title">{{
                    t('administration.members.delete_title')
                }}</component>
                <component :is="Modal.Description">
                    {{
                        t('administration.members.delete_description', {
                            name: displayName,
                        })
                    }}
                </component>
            </component>
            <component :is="Modal.Footer">
                <component :is="Modal.Close" as-child>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </component>
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
            </component>
        </component>
    </component>
</template>
