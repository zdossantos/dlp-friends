<script setup lang="ts">
import { Form, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import UserDataExportController from '@/actions/App/Http/Controllers/Settings/UserDataExportController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    isUserDataExportPreparing,
    userDataExportBecameReady,
} from '@/lib/userDataExport';
import type { UserDataExportState } from '@/types';

const props = defineProps<{ exportState: UserDataExportState }>();

const { t } = useTranslations();
const awaitingRequestedExport = ref(false);
const preparing = computed(() =>
    isUserDataExportPreparing(props.exportState?.status),
);
const { start, stop } = usePoll(
    1500,
    { only: ['dataExport'] },
    { autoStart: false },
);

const announceReady = (): void => {
    awaitingRequestedExport.value = false;
    stop();
    toast.success(t('account.export.ready_toast'));
};

const onRequestSuccess = (): void => {
    awaitingRequestedExport.value = true;

    if (props.exportState?.status === 'ready') {
        announceReady();
    }
};

watch(
    () => props.exportState?.status,
    (status, previousStatus) => {
        if (isUserDataExportPreparing(status)) {
            awaitingRequestedExport.value = true;
            start();

            return;
        }

        stop();

        if (
            awaitingRequestedExport.value &&
            userDataExportBecameReady(previousStatus, status)
        ) {
            announceReady();
        }
    },
    { immediate: true },
);
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
            @success="onRequestSuccess"
            v-slot="{ processing }"
        >
            <Button
                :disabled="processing || preparing"
                :aria-busy="processing || preparing ? 'true' : undefined"
                data-test="request-data-export"
            >
                <Spinner v-if="processing || preparing" />
                {{
                    t(
                        processing || preparing
                            ? 'account.export.preparing'
                            : 'account.export.request',
                    )
                }}
            </Button>
        </Form>
    </div>
</template>
