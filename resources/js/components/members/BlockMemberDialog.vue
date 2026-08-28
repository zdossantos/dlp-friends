<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldBan } from '@lucide/vue';
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
import { block as blockMember } from '@/routes/members';

const props = defineProps<{ memberId: number; returnHref: string }>();
const { t } = useTranslations();
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
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
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
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('blocking.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('blocking.description') }}
                </DialogDescription>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                {{ t('blocking.effects') }}
            </p>
            <p v-if="failed" role="alert" class="text-sm text-destructive">
                {{ t('blocking.error') }}
            </p>
            <DialogFooter>
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="submitting"
                    >
                        {{ t('blocking.cancel') }}
                    </Button>
                </DialogClose>
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
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
