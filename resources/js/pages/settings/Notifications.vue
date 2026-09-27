<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { BellRing } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import WebPushDevices from '@/components/settings/WebPushDevices.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { useWebPush } from '@/composables/useWebPush';
import { edit } from '@/routes/notification-preferences';

const props = defineProps<{
    preferences: Record<string, boolean>;
    devices: Array<{
        uuid: string;
        deviceName: string | null;
        platform: string | null;
        lastUsedAt: string | null;
    }>;
    vapidPublicKey: string;
}>();
const { t } = useTranslations();
const push = useWebPush(props.vapidPublicKey);
const categories = [
    'messages',
    'matches',
    'events',
    'partner_announcements',
    'administration',
] as const;

setLayoutProps({
    breadcrumbs: [
        { title: t('account.settings.notifications.navigation'), href: edit() },
    ],
});
</script>

<template>
    <Head :title="t('account.settings.notifications.title')" />
    <h1 class="sr-only">{{ t('account.settings.notifications.title') }}</h1>
    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t('account.settings.notifications.title')"
            :description="t('account.settings.notifications.description')"
        />

        <section
            class="space-y-3 rounded-2xl border p-4"
            aria-labelledby="push-status-title"
        >
            <div class="flex gap-3">
                <BellRing
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <h2 id="push-status-title" class="font-medium">
                        {{ t('account.settings.notifications.push_title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('account.settings.notifications.push_help') }}
                    </p>
                </div>
            </div>
            <p
                v-if="push.permission.value === 'denied'"
                role="status"
                class="text-sm text-destructive"
            >
                {{ t('account.settings.notifications.permission_denied') }}
            </p>
            <p
                v-else-if="!push.supported.value"
                class="text-sm text-muted-foreground"
            >
                {{ t('account.settings.notifications.push_unavailable') }}
            </p>
            <div v-else class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    class="min-h-11"
                    :disabled="push.busy.value || push.subscribed.value"
                    data-test="enable-web-push"
                    @click="push.enable"
                >
                    <Spinner v-if="push.busy.value" />
                    {{
                        push.subscribed.value
                            ? t('account.settings.notifications.push_enabled')
                            : t('account.settings.notifications.push_enable')
                    }}
                </Button>
                <Button
                    v-if="push.subscribed.value"
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="push.busy.value"
                    @click="push.disableCurrent"
                >
                    {{
                        t('account.settings.notifications.push_disable_device')
                    }}
                </Button>
            </div>
            <p
                v-if="push.error.value"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ t('account.settings.notifications.push_error') }}
            </p>
        </section>

        <Form
            action="/settings/notifications"
            method="patch"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <div
                v-for="category in categories"
                :key="category"
                class="rounded-2xl border p-4"
            >
                <label class="flex min-h-11 items-start gap-3 text-sm">
                    <input type="hidden" :name="category" value="0" />
                    <Switch
                        :name="category"
                        value="1"
                        :default-value="preferences[category] ?? true"
                        class="mt-0.5"
                        :data-test="
                            category === 'partner_announcements'
                                ? 'partner-announcements-switch'
                                : `notification-${category}-switch`
                        "
                    />
                    <span class="space-y-1">
                        <span class="block font-medium">{{
                            category === 'partner_announcements'
                                ? t(
                                      'account.settings.notifications.partner_announcements',
                                  )
                                : t(
                                      `account.settings.notifications.categories.${category}`,
                                  )
                        }}</span>
                        <span class="block text-muted-foreground">{{
                            category === 'partner_announcements'
                                ? t(
                                      'account.settings.notifications.partner_announcements_help',
                                  )
                                : t(
                                      `account.settings.notifications.categories_help.${category}`,
                                  )
                        }}</span>
                    </span>
                </label>
                <InputError class="mt-2" :message="errors[category]" />
            </div>
            <Button
                :disabled="processing"
                :aria-busy="processing ? 'true' : undefined"
                data-test="save-notification-preferences"
                class="min-h-11"
            >
                <Spinner v-if="processing" />{{ t('account.settings.save') }}
            </Button>
        </Form>

        <WebPushDevices :devices="devices" />
    </div>
</template>
