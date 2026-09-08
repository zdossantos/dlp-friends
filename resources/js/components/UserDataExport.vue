<script setup lang="ts">
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import UserDataExportController from '@/actions/App/Http/Controllers/Settings/UserDataExportController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
import { userDataExportDownload } from '@/lib/userDataExport';

const { t } = useTranslations();
const generating = ref(false);

const download = async (): Promise<void> => {
    generating.value = true;

    try {
        const result = await userDataExportDownload(() =>
            fetch(UserDataExportController.store.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...xsrfHeader(document.cookie),
                },
            }),
        );
        const url = URL.createObjectURL(result.blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = result.filename;
        link.click();
        URL.revokeObjectURL(url);
    } catch {
        toast.error(t('account.export.failed'));
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('account.export.title')"
            :description="t('account.export.description')"
        />
        <Button
            type="button"
            :disabled="generating"
            :aria-busy="generating ? 'true' : undefined"
            data-test="download-data-export"
            @click="download"
        >
            <Spinner v-if="generating" />
            {{
                t(
                    generating
                        ? 'account.export.generating'
                        : 'account.export.download',
                )
            }}
        </Button>
    </div>
</template>
