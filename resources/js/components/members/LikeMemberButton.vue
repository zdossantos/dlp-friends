<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserRoundPlus } from '@lucide/vue';
import { ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { like as likeMember } from '@/routes/members';

const props = defineProps<{
    memberId: number;
    actionHref?: string;
    label?: string;
    returnHref?: string;
    dataTest?: string;
}>();
const { t } = useTranslations();
const submitting = ref(false);
const error = ref<string | null>(null);

function submit(): void {
    router.post(
        props.actionHref ?? likeMember(props.memberId).url,
        props.returnHref ? { return_to: props.returnHref } : {},
        {
            preserveScroll: true,
            onStart: () => {
                submitting.value = true;
                error.value = null;
            },
            onError: (errors) => {
                error.value = String(
                    errors.target ??
                        errors.decision ??
                        t('discovery.page.generic_error'),
                );
            },
            onHttpException: () => {
                error.value = t('discovery.page.server_error');

                return false;
            },
            onNetworkError: () => {
                error.value = t('discovery.page.network_error');

                return false;
            },
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <Button
        type="button"
        :data-test="dataTest ?? 'like-member'"
        :disabled="submitting"
        :aria-busy="submitting"
        @click="submit"
    >
        <UserRoundPlus class="size-4" aria-hidden="true" />
        {{
            submitting
                ? t('discovery.actions.adding_friend')
                : (label ?? t('discovery.actions.add_friend'))
        }}
    </Button>
    <Alert v-if="error" variant="destructive" aria-live="assertive">
        <AlertDescription>{{ error }}</AlertDescription>
    </Alert>
</template>
