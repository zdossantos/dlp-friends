<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EventConfirmationDialog from '@/components/events/EventConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { decision, remove } from '@/routes/events/registrations';
import type {
    EventWorkspaceContext,
    OrganizerRegistration,
} from '@/types/event';

const props = defineProps<{
    registrations: OrganizerRegistration[];
    context: EventWorkspaceContext;
}>();
const { t } = useTranslations();
const page = usePage();
const busyRegistrationId = ref<number | null>(null);
const registrationError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;
    return errors?.registration;
});
const origin = props.context === 'mine' ? { origin: 'mine' } : {};

function decide(id: number, accept: boolean): void {
    busyRegistrationId.value = id;
    router.patch(
        decision(id, { query: origin }).url,
        { accept },
        {
            preserveScroll: true,
            onFinish: () => (busyRegistrationId.value = null),
        },
    );
}
function removeMember(id: number): void {
    busyRegistrationId.value = id;
    router.delete(remove(id, { query: origin }).url, {
        preserveScroll: true,
        onFinish: () => (busyRegistrationId.value = null),
    });
}
</script>

<template>
    <InputError
        :message="registrationError"
        data-test="organizer-registration-error"
    />
    <ul class="divide-y rounded-2xl border" role="list">
        <li
            v-for="registration in registrations"
            :key="registration.registrationId"
            class="flex items-center justify-between gap-3 p-4"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Avatar class="size-10 shrink-0">
                    <AvatarImage
                        v-if="registration.avatar"
                        :src="registration.avatar.image_url"
                        :alt="
                            registration.displayName ?? registration.avatar.name
                        "
                    />
                    <AvatarFallback>
                        {{
                            registration.displayName
                                ?.slice(0, 1)
                                .toUpperCase() ?? '?'
                        }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0">
                    <p class="font-medium">{{ registration.displayName }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ t(`events.statuses.${registration.status}`) }}
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 gap-2">
                <template v-if="registration.status === 'pending'"
                    ><Button
                        size="sm"
                        :disabled="busyRegistrationId !== null"
                        @click="decide(registration.registrationId, true)"
                        >{{ t('events.actions.accept') }}</Button
                    ><EventConfirmationDialog
                        :title="t('events.confirmations.refuse_title')"
                        :description="
                            t('events.confirmations.refuse_description')
                        "
                        :confirm-label="t('events.actions.refuse')"
                        :busy="
                            busyRegistrationId === registration.registrationId
                        "
                        destructive
                        @confirm="decide(registration.registrationId, false)"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="busyRegistrationId !== null"
                        >
                            {{ t('events.actions.refuse') }}
                        </Button>
                    </EventConfirmationDialog></template
                >
                <EventConfirmationDialog
                    v-if="registration.status === 'accepted'"
                    :title="t('events.confirmations.remove_title')"
                    :description="t('events.confirmations.remove_description')"
                    :confirm-label="t('events.actions.remove')"
                    :busy="busyRegistrationId === registration.registrationId"
                    destructive
                    @confirm="removeMember(registration.registrationId)"
                >
                    <Button
                        size="sm"
                        variant="destructive"
                        :disabled="busyRegistrationId !== null"
                    >
                        {{ t('events.actions.remove') }}
                    </Button>
                </EventConfirmationDialog>
            </div>
        </li>
    </ul>
</template>
