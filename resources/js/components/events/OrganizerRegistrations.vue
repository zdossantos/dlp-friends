<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { decision, remove } from '@/routes/events/registrations';
import type { OrganizerRegistration } from '@/types/event';

defineProps<{ registrations: OrganizerRegistration[] }>();
const { t } = useTranslations();
function decide(id: number, accept: boolean): void {
    if (!accept && !window.confirm(t('events.confirmations.refuse_title'))) {
        return;
    }

    router.patch(decision(id).url, { accept }, { preserveScroll: true });
}
function removeMember(id: number): void {
    if (window.confirm(t('events.confirmations.remove_title'))) {
        router.delete(remove(id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <ul class="divide-y rounded-2xl border" role="list">
        <li
            v-for="registration in registrations"
            :key="registration.registrationId"
            class="flex items-center justify-between gap-3 p-4"
        >
            <div>
                <p class="font-medium">{{ registration.displayName }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ t(`events.statuses.${registration.status}`) }}
                </p>
            </div>
            <div class="flex gap-2">
                <template v-if="registration.status === 'pending'"
                    ><Button
                        size="sm"
                        @click="decide(registration.registrationId, true)"
                        >{{ t('events.actions.accept') }}</Button
                    ><Button
                        size="sm"
                        variant="outline"
                        @click="decide(registration.registrationId, false)"
                        >{{ t('events.actions.refuse') }}</Button
                    ></template
                >
                <Button
                    v-if="registration.status === 'accepted'"
                    size="sm"
                    variant="destructive"
                    @click="removeMember(registration.registrationId)"
                    >{{ t('events.actions.remove') }}</Button
                >
            </div>
        </li>
    </ul>
</template>
