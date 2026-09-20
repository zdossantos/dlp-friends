<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Handshake, MessageCircle, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { read } from '@/routes/notifications';
import type { MemberNotification } from '@/types/notification';

const props = defineProps<{ notification: MemberNotification }>();
const openProcessing = ref(false);
const dismissProcessing = ref(false);
const { t, formatDate } = useTranslations();
const title = computed(() =>
    t(props.notification.translation_key, props.notification.parameters),
);

function openNotification(): void {
    router.patch(
        read(props.notification.id).url,
        {},
        {
            onStart: () => (openProcessing.value = true),
            onFinish: () => (openProcessing.value = false),
        },
    );
}

function dismissNotification(): void {
    if (
        props.notification.dismiss_url === undefined ||
        !window.confirm(
            t('notifications.actions.confirm_dismiss_partner_announcement'),
        )
    ) {
        return;
    }

    router.delete(props.notification.dismiss_url, {
        preserveScroll: true,
        onStart: () => (dismissProcessing.value = true),
        onFinish: () => (dismissProcessing.value = false),
    });
}
</script>

<template>
    <li
        class="flex max-w-full min-w-0 items-stretch overflow-hidden"
        :class="notification.read_at === null ? 'bg-primary/8' : ''"
    >
        <Button
            type="button"
            variant="ghost"
            :data-test="`notification-${notification.id}`"
            class="h-auto max-w-full min-w-0 flex-1 items-start justify-start gap-3 overflow-hidden rounded-none px-4 py-4 text-left hover:bg-muted/60 focus-visible:ring-inset"
            :disabled="openProcessing || dismissProcessing"
            :aria-busy="openProcessing"
            @click="openNotification"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-2xl bg-secondary text-secondary-foreground"
            >
                <CalendarDays
                    v-if="notification.category === 'events'"
                    class="size-5"
                    aria-hidden="true"
                />
                <Handshake
                    v-else-if="notification.category === 'partners'"
                    class="size-5"
                    aria-hidden="true"
                />
                <MessageCircle v-else class="size-5" aria-hidden="true" />
            </span>
            <span class="min-w-0 flex-1">
                <span
                    data-test="notification-title"
                    class="block truncate text-sm"
                    :class="
                        notification.read_at === null ? 'font-semibold' : ''
                    "
                    :title="title"
                >
                    {{ title }}
                </span>
                <time
                    v-if="notification.created_at"
                    :datetime="notification.created_at"
                    class="mt-1 block text-xs text-muted-foreground"
                >
                    {{
                        formatDate(notification.created_at, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })
                    }}
                </time>
                <span
                    v-if="notification.action_label"
                    class="mt-2 block text-xs font-semibold text-primary"
                >
                    {{ notification.action_label }}
                </span>
            </span>
            <span
                v-if="notification.read_at === null"
                class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                :aria-label="t('notifications.items.unread')"
            />
        </Button>
        <Button
            v-if="notification.dismiss_url"
            type="button"
            variant="ghost"
            size="icon"
            class="m-2 size-11 shrink-0 self-center"
            :data-test="`notification-dismiss-${notification.id}`"
            :disabled="dismissProcessing || openProcessing"
            :aria-busy="dismissProcessing"
            :aria-label="
                t('notifications.actions.dismiss_partner_announcement')
            "
            @click.stop="dismissNotification"
        >
            <X class="size-5" aria-hidden="true" />
        </Button>
    </li>
</template>
