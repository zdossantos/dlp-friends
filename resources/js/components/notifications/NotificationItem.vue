<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, MessageCircle } from '@lucide/vue';
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { read } from '@/routes/notifications';
import type { MemberNotification } from '@/types/notification';

const props = defineProps<{ notification: MemberNotification }>();
const processing = ref(false);
const { t, formatDate } = useTranslations();

function openNotification(): void {
    router.patch(
        read(props.notification.id).url,
        {},
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <li>
        <button
            type="button"
            class="flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset disabled:opacity-60"
            :class="notification.read_at === null ? 'bg-primary/8' : ''"
            :disabled="processing"
            :aria-busy="processing"
            @click="openNotification"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-2xl bg-secondary text-primary"
            >
                <CalendarDays
                    v-if="notification.category === 'events'"
                    class="size-5"
                    aria-hidden="true"
                />
                <MessageCircle v-else class="size-5" aria-hidden="true" />
            </span>
            <span class="min-w-0 flex-1">
                <span
                    class="block text-sm"
                    :class="
                        notification.read_at === null ? 'font-semibold' : ''
                    "
                >
                    {{
                        t(notification.translation_key, notification.parameters)
                    }}
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
            </span>
            <span
                v-if="notification.read_at === null"
                class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                :aria-label="t('notifications.items.unread')"
            />
        </button>
    </li>
</template>
