<script setup lang="ts">
import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { BellRing } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import WebPushDevices from '@/components/settings/WebPushDevices.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { useWebPush } from '@/composables/useWebPush';
import {
    notificationSettingsAction,
    notificationSettingsView,
    WEB_PUSH_INVITATION_OPEN_EVENT,
} from '@/lib/pwa/webPushInvitation';
import { edit } from '@/routes/notification-preferences';

const props = defineProps<{
    preferences: Record<string, boolean>;
    emailPreferences: Record<string, boolean>;
    adminNewMemberAlertsEnabled: boolean | null;
    devices: Array<{
        uuid: string;
        deviceName: string | null;
        platform: string | null;
        lastUsedAt: string | null;
    }>;
    vapidPublicKey: string;
}>();
const { t } = useTranslations();
const page = usePage();
const push = useWebPush(props.vapidPublicKey, page.props.auth.user.id);
const pushStatusView = computed(() =>
    notificationSettingsView({
        permission: push.permission.value,
        supported: push.supported.value,
        secureContext: push.secureContext.value,
    }),
);
const categories = computed(() => Object.keys(props.preferences));

async function disableAll(): Promise<void> {
    if (
        window.confirm(t('account.settings.notifications.disable_all_confirm'))
    ) {
        try {
            await push.disableCurrent();
        } finally {
            router.delete('/settings/notifications', { preserveScroll: true });
        }
    }
}

async function enableOrExplainPush(): Promise<void> {
    const action = notificationSettingsAction(push.permission.value);

    if (action === 'subscribe') {
        await push.enable();

        return;
    }

    window.dispatchEvent(new Event(WEB_PUSH_INVITATION_OPEN_EVENT));
}

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
                v-if="pushStatusView === 'permission-denied'"
                role="status"
                class="text-sm text-destructive"
            >
                {{ t('account.settings.notifications.permission_denied') }}
            </p>
            <p
                v-else-if="pushStatusView === 'insecure-context'"
                role="status"
                class="text-sm text-destructive"
            >
                {{ t('account.settings.notifications.https_required') }}
            </p>
            <p
                v-else-if="pushStatusView === 'unsupported'"
                class="text-sm text-muted-foreground"
            >
                {{ t('account.settings.notifications.push_unavailable') }}
            </p>
            <div
                v-if="
                    pushStatusView === 'controls' ||
                    pushStatusView === 'permission-denied'
                "
                class="flex flex-wrap gap-2"
            >
                <Button
                    type="button"
                    class="min-h-11"
                    :disabled="push.busy.value || push.subscribed.value"
                    data-test="enable-web-push"
                    @click="enableOrExplainPush"
                >
                    <Spinner v-if="push.busy.value" />
                    {{
                        push.subscribed.value
                            ? t('account.settings.notifications.push_enabled')
                            : push.permission.value === 'denied'
                              ? t(
                                    'account.settings.notifications.show_instructions',
                                )
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
            <section class="space-y-4" aria-labelledby="email-recap-title">
                <div>
                    <h2 id="email-recap-title" class="font-medium">
                        {{ t('account.settings.notifications.email_title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('account.settings.notifications.email_help') }}
                    </p>
                </div>
                <div
                    v-for="(enabled, category) in emailPreferences"
                    :key="category"
                    class="rounded-2xl border p-4"
                >
                    <label class="flex min-h-11 items-start gap-3 text-sm">
                        <input type="hidden" :name="category" value="0" />
                        <Switch
                            :key="`${category}-${enabled}`"
                            :name="category"
                            value="1"
                            :default-value="enabled"
                            class="mt-0.5"
                            :data-test="`email-${category}-switch`"
                        />
                        <span class="space-y-1">
                            <span class="block font-medium">{{
                                t(`account.settings.notifications.${category}`)
                            }}</span>
                            <span class="block text-muted-foreground">{{
                                t(
                                    `account.settings.notifications.${category}_help`,
                                )
                            }}</span>
                        </span>
                    </label>
                    <InputError class="mt-2" :message="errors[category]" />
                </div>
            </section>
            <div
                v-if="adminNewMemberAlertsEnabled !== null"
                class="rounded-2xl border p-4"
            >
                <label class="flex min-h-11 items-start gap-3 text-sm">
                    <input
                        type="hidden"
                        name="admin_new_member_alerts"
                        value="0"
                    />
                    <Switch
                        :key="String(adminNewMemberAlertsEnabled)"
                        name="admin_new_member_alerts"
                        value="1"
                        :default-value="adminNewMemberAlertsEnabled"
                        class="mt-0.5"
                        data-test="admin-new-member-alerts-switch"
                    />
                    <span class="space-y-1">
                        <span class="block font-medium">{{
                            t(
                                'account.settings.notifications.new_member_alerts',
                            )
                        }}</span>
                        <span class="block text-muted-foreground">{{
                            t(
                                'account.settings.notifications.new_member_alerts_help',
                            )
                        }}</span>
                    </span>
                </label>
                <InputError
                    class="mt-2"
                    :message="errors.admin_new_member_alerts"
                />
            </div>
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
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                data-test="disable-all-notifications"
                @click="disableAll"
            >
                {{ t('account.settings.notifications.disable_all') }}
            </Button>
        </Form>

        <WebPushDevices :devices="devices" />
    </div>
</template>
