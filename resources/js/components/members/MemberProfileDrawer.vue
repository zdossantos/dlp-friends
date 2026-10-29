<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import EmbeddedMemberProfile from '@/components/members/EmbeddedMemberProfile.vue';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerTitle,
} from '@/components/ui/drawer';
import { useTranslations } from '@/composables/useTranslations';
import { show } from '@/routes/members';
import type { EmbeddedMemberProfile as MemberProfile } from '@/types/member';

const { t } = useTranslations();
const page = usePage();
const open = ref(false);
const loading = ref(false);
const error = ref('');
const profile = shallowRef<MemberProfile | null>(null);
let trigger: HTMLElement | null = null;
let request: AbortController | undefined;

async function openProfile(memberId: number, event: MouseEvent): Promise<void> {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    trigger = event.currentTarget as HTMLElement;
    profile.value = null;
    error.value = '';
    loading.value = true;
    open.value = true;

    try {
        const response = await fetch(show(memberId).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error();
        }

        const data = (await response.json()) as MemberProfile;

        if (!controller.signal.aborted) {
            profile.value = data;
        }
    } catch {
        if (!controller.signal.aborted) {
            error.value = t('conversations.profile.unavailable');
        }
    } finally {
        if (!controller.signal.aborted) {
            loading.value = false;
        }
    }
}

watch(open, (isOpen) => {
    if (!isOpen) {
        request?.abort();
        profile.value = null;
    }
});
onBeforeUnmount(() => request?.abort());

function restoreFocus(event: Event): void {
    event.preventDefault();

    if (trigger?.isConnected) {
        trigger.focus({ preventScroll: true });
    }
}

defineExpose({ openProfile });
</script>

<template>
    <Drawer v-model:open="open">
        <DrawerContent
            data-test="member-profile-drawer"
            tabindex="-1"
            class="mx-auto h-[85svh] max-h-[85svh] w-full max-w-lg overflow-hidden bg-card px-0 pb-[max(1rem,env(safe-area-inset-bottom))] [&>[data-slot=drawer-handle]]:hidden"
            @close-auto-focus="restoreFocus"
        >
            <DrawerDescription class="sr-only">{{
                t('conversations.profile.description')
            }}</DrawerDescription>
            <DrawerTitle class="sr-only">{{
                t('conversations.profile.title')
            }}</DrawerTitle>
            <DrawerClose as-child>
                <Button
                    data-test="member-profile-close"
                    variant="outline"
                    size="icon"
                    class="absolute top-3 right-3 z-30 size-11 rounded-full bg-card"
                    :aria-label="t('common.actions.close')"
                >
                    <X class="size-5" aria-hidden="true" />
                </Button>
            </DrawerClose>
            <p v-if="loading" role="status" class="p-6">
                {{ t('conversations.profile.loading') }}
            </p>
            <p v-else-if="error" role="alert" class="p-6">{{ error }}</p>
            <EmbeddedMemberProfile
                v-else-if="profile"
                :profile="profile"
                :block-return-href="page.url"
                class="min-h-0 flex-1"
                @updated="open = false"
            />
        </DrawerContent>
    </Drawer>
</template>
