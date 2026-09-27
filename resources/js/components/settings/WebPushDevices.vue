<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Smartphone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
const { t } = useTranslations();
const removing = ref<string>();

async function remove(uuid: string): Promise<void> {
    if (!window.confirm(t('account.settings.notifications.devices_confirm'))) {
        return;
    }

    removing.value = uuid;
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
    removing.value = undefined;

    if (response.ok) {
        router.reload({ only: ['devices'] });
    }
}
</script>

<template>
    <section class="space-y-3" aria-labelledby="push-devices-title">
        <div>
            <h2 id="push-devices-title" class="font-medium">
                {{ t('account.settings.notifications.devices_title') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ t('account.settings.notifications.devices_help') }}
            </p>
        </div>
        <p
            v-if="devices.length === 0"
            class="rounded-2xl border p-4 text-sm text-muted-foreground"
        >
            {{ t('account.settings.notifications.devices_empty') }}
        </p>
        <ul v-else class="space-y-2">
            <li
                v-for="device in devices"
                :key="device.uuid"
                class="flex min-h-14 items-center gap-3 rounded-2xl border p-3"
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
                                    date: new Date(
                                        device.lastUsedAt,
                                    ).toLocaleDateString(),
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
                    :disabled="removing === device.uuid"
                    :aria-label="
                        t('account.settings.notifications.device_remove')
                    "
                    @click="remove(device.uuid)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
    </section>
</template>
