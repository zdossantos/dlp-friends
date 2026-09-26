<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import PartnerNotificationPreferenceController from '@/actions/App/Http/Controllers/Settings/PartnerNotificationPreferenceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/notification-preferences';

defineProps<{
    partnerAnnouncementsEnabled: boolean;
}>();

const { t } = useTranslations();
setLayoutProps({
    breadcrumbs: [
        {
            title: t('account.settings.notifications.navigation'),
            href: edit(),
        },
    ],
});
</script>

<template>
    <Head :title="t('account.settings.notifications.title')" />

    <h1 class="sr-only">{{ t('account.settings.notifications.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('account.settings.notifications.title')"
            :description="t('account.settings.notifications.description')"
        />

        <Form
            v-bind="PartnerNotificationPreferenceController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="rounded-2xl border p-4">
                <label class="flex items-start gap-3 text-sm">
                    <input
                        type="hidden"
                        name="partner_announcements"
                        value="0"
                    />
                    <Switch
                        name="partner_announcements"
                        value="1"
                        :default-value="partnerAnnouncementsEnabled"
                        class="mt-0.5"
                        data-test="partner-announcements-switch"
                    />
                    <span class="space-y-1">
                        <span class="block font-medium">
                            {{
                                t(
                                    'account.settings.notifications.partner_announcements',
                                )
                            }}
                        </span>
                        <span class="block text-muted-foreground">
                            {{
                                t(
                                    'account.settings.notifications.partner_announcements_help',
                                )
                            }}
                        </span>
                    </span>
                </label>
                <InputError
                    class="mt-2"
                    :message="errors.partner_announcements"
                />
            </div>

            <Button
                :disabled="processing"
                :aria-busy="processing ? 'true' : undefined"
                data-test="save-notification-preferences"
                class="min-h-11"
            >
                <Spinner v-if="processing" />
                {{ t('account.settings.save') }}
            </Button>
        </Form>
    </div>
</template>
