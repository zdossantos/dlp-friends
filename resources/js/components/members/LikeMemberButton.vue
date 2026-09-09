<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserRoundPlus } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { like as likeMember } from '@/routes/members';

const props = defineProps<{ memberId: number }>();
const { t } = useTranslations();
const submitting = ref(false);

function submit(): void {
    router.post(
        likeMember(props.memberId).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <Button
        type="button"
        data-test="like-member"
        :disabled="submitting"
        :aria-busy="submitting"
        @click="submit"
    >
        <UserRoundPlus class="size-4" aria-hidden="true" />
        {{
            submitting
                ? t('discovery.actions.adding_friend')
                : t('discovery.actions.add_friend')
        }}
    </Button>
</template>
