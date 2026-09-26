<script setup lang="ts">
import { Bell, Download, Share } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { usePwa } from '@/composables/usePwa';
import { useTranslations } from '@/composables/useTranslations';

const emit = defineEmits<{ complete: [] }>();
const { t } = useTranslations();
const { state, canInstall, install, isStandalone } = usePwa();
const busy = ref(false);
const isIos = computed(
    () =>
        typeof navigator !== 'undefined' &&
        /iPad|iPhone|iPod/.test(navigator.userAgent),
);

async function requestInstall(): Promise<void> {
    busy.value = true;
    await install();
    busy.value = false;
}

function skip(): void {
    if (window.confirm(t('onboarding.install.skip_confirmation'))) {
        emit('complete');
    }
}
</script>

<template>
    <section
        class="flex w-full max-w-xl flex-col items-center gap-5 rounded-3xl border bg-card p-6 text-center shadow-sm sm:p-8"
        data-test="install-app-step"
    >
        <div
            class="flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"
        >
            <Download class="size-8" aria-hidden="true" />
        </div>
        <div class="space-y-2">
            <h1
                class="text-2xl font-semibold"
                data-test="install-app-heading"
                tabindex="-1"
            >
                {{ t('onboarding.install.title') }}
            </h1>
            <p class="text-muted-foreground">
                {{ t('onboarding.install.description') }}
            </p>
        </div>

        <div
            v-if="isStandalone || state === 'installed'"
            class="w-full rounded-2xl bg-secondary p-4 text-secondary-foreground"
            role="status"
        >
            {{ t('onboarding.install.installed') }}
        </div>
        <Button
            v-else-if="canInstall"
            class="min-h-11 w-full sm:w-auto"
            type="button"
            :disabled="busy"
            @click="requestInstall"
        >
            <Download aria-hidden="true" />
            {{ t('onboarding.install.action') }}
        </Button>
        <div
            v-else-if="isIos"
            class="w-full space-y-3 rounded-2xl bg-muted p-4 text-left"
        >
            <p class="font-medium">{{ t('onboarding.install.ios_title') }}</p>
            <p class="flex gap-3 text-sm text-muted-foreground">
                <Share class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                {{ t('onboarding.install.ios_instructions') }}
            </p>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            {{ t('onboarding.install.unavailable') }}
        </p>

        <div
            class="flex w-full items-start gap-3 rounded-2xl border p-4 text-left"
        >
            <Bell
                class="mt-0.5 size-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <p class="text-sm text-muted-foreground">
                {{ t('onboarding.install.notifications_preview') }}
            </p>
        </div>

        <div class="flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
            <Button
                v-if="isStandalone || state === 'installed'"
                class="min-h-11"
                type="button"
                @click="emit('complete')"
            >
                {{ t('onboarding.install.finish') }}
            </Button>
            <Button
                class="min-h-11"
                data-test="skip-install-app"
                type="button"
                variant="ghost"
                @click="skip"
            >
                {{ t('onboarding.install.skip') }}
            </Button>
        </div>
    </section>
</template>
