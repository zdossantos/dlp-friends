<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BellRing } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { usePwa } from '@/composables/usePwa';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import { useWebPush } from '@/composables/useWebPush';
import { isIosDevice } from '@/lib/pwa/capabilities';
import {
    invitationStorageKey,
    shouldOpenWebPushInvitation,
    WEB_PUSH_INVITATION_OPEN_EVENT,
} from '@/lib/pwa/webPushInvitation';

const page = usePage();
const { t } = useTranslations();
const { isStandalone } = usePwa();
const { isDesktop, Modal } = useResponsiveModal();
const push = useWebPush(
    page.props.webPush.vapidPublicKey,
    page.props.auth.user.id,
);
const open = ref(false);
const storageKey = invitationStorageKey(page.props.auth.user.id);
const dismissed = ref(
    localStorage.getItem(storageKey) === 'true' ||
        localStorage.getItem('web-push-opted-out') === 'true',
);
const showingInstructions = computed(() => push.permission.value === 'denied');
const isIos = isIosDevice(navigator);

const rememberDismissal = (): void => {
    dismissed.value = true;
    localStorage.setItem(storageKey, 'true');
};

const updateOpen = (value: boolean): void => {
    if (!value && open.value && !showingInstructions.value) {
        rememberDismissal();
    }

    open.value = value;
};

const activate = async (): Promise<void> => {
    const enabled = await push.enable();

    if (!enabled) {
        rememberDismissal();
    }

    open.value = false;
};

const openManually = (): void => {
    open.value = true;
};

watch(
    [
        isStandalone,
        push.supported,
        push.initialized,
        push.subscribed,
        push.permission,
    ],
    () => {
        if (
            shouldOpenWebPushInvitation({
                standalone: isStandalone.value,
                supported: push.supported.value,
                initialized: push.initialized.value,
                subscribed: push.subscribed.value,
                permission: push.permission.value,
                dismissed: dismissed.value,
            })
        ) {
            open.value = true;
        }
    },
    { immediate: true },
);

onMounted(() =>
    window.addEventListener(WEB_PUSH_INVITATION_OPEN_EVENT, openManually),
);
onBeforeUnmount(() =>
    window.removeEventListener(WEB_PUSH_INVITATION_OPEN_EVENT, openManually),
);
</script>

<template>
    <component :is="Modal.Root" :open="open" @update:open="updateOpen">
        <component
            :is="Modal.Content"
            data-test="web-push-invitation"
            :class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"
        >
            <component :is="Modal.Header" class="space-y-3">
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary sm:mx-0"
                >
                    <BellRing class="size-6" aria-hidden="true" />
                </div>
                <component :is="Modal.Title">
                    {{
                        t(
                            showingInstructions
                                ? 'account.settings.notifications.invitation.denied_title'
                                : 'account.settings.notifications.invitation.title',
                        )
                    }}
                </component>
                <component :is="Modal.Description" class="space-y-3">
                    <span class="block">
                        {{
                            t(
                                showingInstructions
                                    ? 'account.settings.notifications.invitation.denied_description'
                                    : 'account.settings.notifications.invitation.description',
                            )
                        }}
                    </span>
                    <span
                        v-if="showingInstructions"
                        class="block font-medium text-foreground"
                    >
                        {{
                            t(
                                isIos
                                    ? 'account.settings.notifications.invitation.ios_steps'
                                    : 'account.settings.notifications.invitation.browser_steps',
                            )
                        }}
                    </span>
                    <span v-else class="block">
                        {{
                            t(
                                'account.settings.notifications.invitation.privacy',
                            )
                        }}
                    </span>
                </component>
            </component>
            <component :is="Modal.Footer" class="gap-2">
                <Button
                    v-if="!showingInstructions"
                    type="button"
                    variant="outline"
                    :disabled="push.busy.value"
                    @click="
                        rememberDismissal();
                        open = false;
                    "
                >
                    {{ t('account.settings.notifications.invitation.decline') }}
                </Button>
                <Button
                    v-if="!showingInstructions"
                    type="button"
                    :disabled="push.busy.value"
                    data-test="accept-web-push"
                    @click="activate"
                >
                    <Spinner v-if="push.busy.value" />
                    {{ t('account.settings.notifications.push_enable') }}
                </Button>
                <Button v-else type="button" @click="open = false">
                    {{ t('common.actions.close') }}
                </Button>
            </component>
        </component>
    </component>
</template>
