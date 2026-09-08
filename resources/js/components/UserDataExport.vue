<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import UserDataExportController from '@/actions/App/Http/Controllers/Settings/UserDataExportController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { UserDataExportState } from '@/types';

defineProps<{ exportState: UserDataExportState }>();

const { t } = useTranslations();
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('account.export.title')"
            :description="t('account.export.description')"
        />
        <p v-if="exportState" class="text-sm text-muted-foreground">
            {{ t(`account.export.${exportState.status}`) }}
        </p>
        <Button v-if="exportState?.download_url" as-child>
            <a
                :href="exportState.download_url"
                data-test="download-data-export"
            >
                {{ t('account.export.download') }}
            </a>
        </Button>
        <Form
            v-else
            v-bind="UserDataExportController.store.form()"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <Button
                :disabled="processing || exportState?.status === 'processing'"
                :aria-busy="processing ? 'true' : undefined"
                data-test="request-data-export"
            >
                <Spinner v-if="processing" />
                {{ t('account.export.request') }}
            </Button>
        </Form>
    </div>
</template>
