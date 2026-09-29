<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Handshake,
    MessageCircle,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    notificationSwipeTransition,
    resolveNotificationSwipe,
    shouldCloseNotificationSwipe,
} from '@/lib/notificationSwipe';
import type { SwipePoint } from '@/lib/notificationSwipe';
import { read } from '@/routes/notifications';
import type { MemberNotification } from '@/types/notification';

const actionWidth = 112;
const props = withDefaults(
    defineProps<{ notification: MemberNotification; open?: boolean }>(),
    { open: false },
);
const emit = defineEmits<{ requestOpen: [open: boolean] }>();
const openProcessing = ref(false);
const readProcessing = ref(false);
const deleteProcessing = ref(false);
const pointerStart = ref<SwipePoint | null>(null);
const dragOffset = ref<number | null>(null);
const horizontalDrag = ref(false);
const { t, formatDate } = useTranslations();
const title = computed(() =>
    t(props.notification.translation_key, props.notification.parameters),
);
const reducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)',
).matches;
const translateX = computed(
    () => dragOffset.value ?? (props.open ? -actionWidth : 0),
);
const foregroundStyle = computed(() => ({
    transform: `translateX(${translateX.value}px)`,
    transition: horizontalDrag.value
        ? 'none'
        : notificationSwipeTransition(reducedMotion),
}));

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

function markRead(): void {
    router.patch(
        props.notification.read_url,
        {},
        {
            preserveScroll: true,
            onStart: () => (readProcessing.value = true),
            onError: () => emit('requestOpen', false),
            onFinish: () => (readProcessing.value = false),
        },
    );
}

function deleteNotification(): void {
    const confirmationKey = props.notification.dismiss_url
        ? 'notifications.actions.confirm_dismiss_partner_announcement'
        : 'notifications.actions.confirm_delete';

    if (!window.confirm(t(confirmationKey))) {
        return;
    }

    router.delete(props.notification.delete_url, {
        preserveScroll: true,
        onStart: () => (deleteProcessing.value = true),
        onError: () => emit('requestOpen', false),
        onFinish: () => (deleteProcessing.value = false),
    });
}

function startSwipe(event: PointerEvent): void {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
    }

    pointerStart.value = { x: event.clientX, y: event.clientY };
    dragOffset.value = 0;
    horizontalDrag.value = false;
}

function moveSwipe(event: PointerEvent): void {
    if (pointerStart.value === null) {
        return;
    }

    const resolution = resolveNotificationSwipe(
        pointerStart.value,
        { x: event.clientX, y: event.clientY },
        actionWidth,
    );

    if (resolution.axis === 'vertical') {
        resetSwipe();

        return;
    }

    if (resolution.axis === 'horizontal') {
        horizontalDrag.value = true;

        if (event.currentTarget instanceof HTMLElement) {
            event.currentTarget.setPointerCapture(event.pointerId);
        }

        event.preventDefault();
        dragOffset.value = resolution.offset;
    }
}

function finishSwipe(event: PointerEvent): void {
    if (pointerStart.value === null) {
        return;
    }

    const resolution = resolveNotificationSwipe(
        pointerStart.value,
        { x: event.clientX, y: event.clientY },
        actionWidth,
    );

    emit('requestOpen', resolution.open);
    resetSwipe();
}

function resetSwipe(): void {
    pointerStart.value = null;
    dragOffset.value = null;
    horizontalDrag.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (shouldCloseNotificationSwipe(event.key)) {
        emit('requestOpen', false);
    }
}
</script>

<template>
    <li
        class="relative max-w-full min-w-0 overflow-hidden"
        :class="notification.read_at === null ? 'bg-primary/8' : ''"
        :data-test="`notification-row-${notification.id}`"
        :data-swipe-open="open ? 'true' : 'false'"
        @keydown="handleKeydown"
    >
        <div
            class="absolute inset-y-0 right-0 z-0 flex w-28 items-stretch"
            :aria-label="t('notifications.actions.item_actions')"
        >
            <Button
                type="button"
                variant="secondary"
                class="h-full min-w-0 flex-1 rounded-none px-0"
                :data-test="`notification-mark-read-${notification.id}`"
                :disabled="notification.read_at !== null || readProcessing"
                :aria-busy="readProcessing"
                :aria-label="t('notifications.actions.mark_read')"
                @focus="emit('requestOpen', true)"
                @click="markRead"
                ><Check class="size-5" aria-hidden="true"
            /></Button>
            <Button
                type="button"
                variant="destructive"
                class="h-full min-w-0 flex-1 rounded-none px-0"
                :data-test="`notification-delete-${notification.id}`"
                :disabled="deleteProcessing"
                :aria-busy="deleteProcessing"
                :aria-label="t('notifications.actions.delete')"
                @focus="emit('requestOpen', true)"
                @click="deleteNotification"
                ><Trash2 class="size-5" aria-hidden="true"
            /></Button>
        </div>

        <div
            class="relative z-10 flex min-w-0 touch-pan-y bg-card will-change-transform"
            :data-test="`notification-foreground-${notification.id}`"
            :class="notification.read_at === null ? 'bg-primary/8' : ''"
            :style="foregroundStyle"
            @pointerdown="startSwipe"
            @pointermove="moveSwipe"
            @pointerup="finishSwipe"
            @pointercancel="resetSwipe"
        >
            <Button
                type="button"
                variant="ghost"
                :data-test="`notification-${notification.id}`"
                class="h-auto max-w-full min-w-0 flex-1 items-start justify-start gap-3 overflow-hidden rounded-none px-4 py-4 text-left hover:bg-muted/60 focus-visible:ring-inset"
                :disabled="openProcessing || deleteProcessing"
                :aria-busy="openProcessing"
                @click="openNotification"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-secondary text-secondary-foreground"
                >
                    <img
                        v-if="notification.image_url"
                        :src="notification.image_url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <CalendarDays
                        v-else-if="notification.category === 'events'"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <Handshake
                        v-else-if="notification.category === 'partners'"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <ShieldCheck
                        v-else-if="notification.category === 'administration'"
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
                        >{{ title }}</span
                    >
                    <time
                        v-if="notification.created_at"
                        :datetime="notification.created_at"
                        class="mt-1 block text-xs text-muted-foreground"
                        >{{
                            formatDate(notification.created_at, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })
                        }}</time
                    >
                    <span
                        v-if="notification.content"
                        data-test="notification-content"
                        class="mt-2 block text-sm font-normal whitespace-pre-line text-foreground"
                        >{{ notification.content }}</span
                    >
                    <span
                        v-if="notification.action_label"
                        class="mt-2 block text-xs font-semibold text-primary"
                        >{{ notification.action_label }}</span
                    >
                </span>
                <span
                    v-if="notification.read_at === null"
                    class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                    :aria-label="t('notifications.items.unread')"
                />
            </Button>
        </div>
    </li>
</template>
