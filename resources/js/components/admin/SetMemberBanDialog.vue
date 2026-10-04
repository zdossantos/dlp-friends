<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/admin/members/ban';
const props = defineProps<{ memberId: number; banned: boolean }>();
const { t } = useTranslations();
const { Modal, isDesktop } = useResponsiveModal();
const open = ref(false);
const form = useForm({ banned: !props.banned, reason: '', confirmed: true });
function submit(): void {
    form.banned = !props.banned;
    form.patch(update(props.memberId).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>
<template>
    <component :is="Modal.Root" v-model:open="open"
        ><component :is="Modal.Trigger" as-child
            ><Button variant="outline" data-test="member-ban-trigger">{{
                banned ? t('moderation.unban') : t('moderation.ban')
            }}</Button></component
        >
        <component
            :is="Modal.Content"
            :class="{ 'px-2 pb-8 *:px-4': !isDesktop }"
            ><component :is="Modal.Header"
                ><component :is="Modal.Title">{{
                    banned ? t('moderation.unban') : t('moderation.ban')
                }}</component
                ><component :is="Modal.Description">{{
                    banned
                        ? t('moderation.unban_warning')
                        : t('moderation.ban_warning')
                }}</component></component
            >
            <form class="grid gap-4" @submit.prevent="submit">
                <Label :for="`ban-reason-${memberId}`">{{
                    t('moderation.decision_reason')
                }}</Label
                ><Textarea
                    :id="`ban-reason-${memberId}`"
                    v-model="form.reason"
                    required
                    maxlength="1000"
                /><InputError :message="form.errors.reason" /><InputError
                    :message="form.errors.confirmed"
                /><InputError :message="form.errors.banned" /><component
                    :is="Modal.Footer"
                    ><component :is="Modal.Close" as-child
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            >{{ t('common.actions.cancel') }}</Button
                        ></component
                    ><Button
                        type="submit"
                        variant="destructive"
                        data-test="confirm-member-ban"
                        :disabled="form.processing"
                        >{{ t('moderation.confirm_decision') }}</Button
                    ></component
                >
            </form>
        </component>
    </component>
</template>
