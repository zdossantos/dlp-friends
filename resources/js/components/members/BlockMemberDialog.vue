<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldBan } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import { block as blockMember } from '@/routes/members';

const props = defineProps<{ memberId: number; returnHref: string }>();
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();
const open = ref(false);
const submitting = ref(false);
const failed = ref(false);

function submit(): void {
    failed.value = false;
    router.post(
        blockMember(props.memberId).url,
        { return_to: props.returnHref },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onError: () => (failed.value = true),
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <component :is="Modal.Root" v-model:open="open">
        <component :is="Modal.Trigger" as-child>
            <Button
                type="button"
                variant="outline"
                dusk="block-member-trigger"
                data-test="block-member-trigger"
                class="text-destructive hover:text-destructive"
            >
                <ShieldBan class="size-4" aria-hidden="true" />
                {{ t('blocking.trigger') }}
            </Button>
        </component>
        <component
            :is="Modal.Content"
            :class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"
        >
            <component :is="Modal.Header">
                <component :is="Modal.Title">{{
                    t('blocking.title')
                }}</component>
                <component :is="Modal.Description">
                    {{ t('blocking.description') }}
                </component>
            </component>
            <p class="text-sm text-muted-foreground">
                {{ t('blocking.effects') }}
            </p>
            <p v-if="failed" role="alert" class="text-sm text-destructive">
                {{ t('blocking.error') }}
            </p>
            <component :is="Modal.Footer">
                <component :is="Modal.Close" as-child>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="submitting"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                </component>
                <Button
                    type="button"
                    variant="destructive"
                    dusk="confirm-block-member"
                    data-test="confirm-block-member"
                    :disabled="submitting"
                    @click="submit"
                >
                    {{
                        submitting
                            ? t('blocking.submitting')
                            : t('blocking.confirm')
                    }}
                </Button>
            </component>
        </component>
    </component>
</template>
