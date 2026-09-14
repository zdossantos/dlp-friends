<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { unblock as unblockMember } from '@/routes/members';

const props = defineProps<{
    memberId: number;
    returnHref: string;
    dataTest?: string;
}>();
const { t } = useTranslations();
const submitting = ref(false);

function submit(): void {
    router.delete(unblockMember(props.memberId).url, {
        data: { return_to: props.returnHref },
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Button
        type="button"
        variant="outline"
        dusk="unblock-member-trigger"
        :data-test="dataTest ?? 'unblock-member-trigger'"
        :disabled="submitting"
        @click="submit"
    >
        <ShieldCheck class="size-4" aria-hidden="true" />
        {{ submitting ? t('blocking.unblocking') : t('blocking.unblock') }}
    </Button>
</template>
