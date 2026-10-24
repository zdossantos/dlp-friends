<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Smartphone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';

defineProps<{
    devices: Array<{
        uuid: string;
        deviceName: string | null;
        platform: string | null;
        lastUsedAt: string | null;
    }>;
}>();
const { t, formatDate } = useTranslations();
const removing = ref<string>();
const failed = ref(false);

async function remove(uuid: string): Promise<void> {
    if (removing.value) {
        return;
    }

    if (!window.confirm(t('account.settings.notifications.devices_confirm'))) {
        return;
    }

    removing.value = uuid;
    failed.value = false;

    try {
        const response = await fetch(
            `/settings/notifications/devices/${encodeURIComponent(uuid)}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...xsrfHeader(document.cookie),
                },
            },
        );

        if (!response.ok && response.status !== 404) {
            throw new Error('device_removal_failed');
        }

        router.reload({ only: ['devices'] });
    } catch {
        failed.value = true;
    } finally {
        removing.value = undefined;
    }
}
</script>

<template>
    <SettingsSection
        heading-id="push-devices-title"
        :title="t('account.settings.notifications.devices_title')"
        :description="t('account.settings.notifications.devices_help')"
    >
        <p v-if="failed" role="alert" class="text-sm text-destructive">
            {{ t('account.settings.notifications.push_error') }}
        </p>
        <p
            v-if="devices.length === 0"
            class="rounded-xl bg-muted/40 p-4 text-sm leading-6 text-muted-foreground"
        >
            {{ t('account.settings.notifications.devices_empty') }}
        </p>
        <ul v-else class="space-y-2">
            <li
                v-for="device in devices"
                :key="device.uuid"
                class="flex min-h-14 items-center gap-3 rounded-xl bg-muted/40 p-3"
            >
                <Smartphone
                    class="size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">{{
                        device.deviceName ||
                        device.platform ||
                        t('account.settings.notifications.device_unknown')
                    }}</span>
                    <span
                        v-if="device.lastUsedAt"
                        class="block text-xs text-muted-foreground"
                        >{{
                            t(
                                'account.settings.notifications.device_last_used',
                                {
                                    date: formatDate(device.lastUsedAt),
                                },
                            )
                        }}</span
                    >
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="min-h-11 min-w-11"
                    :disabled="removing !== undefined"
                    :aria-label="
                        t('account.settings.notifications.device_remove')
                    "
                    @click="remove(device.uuid)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
    </SettingsSection>
</template>
