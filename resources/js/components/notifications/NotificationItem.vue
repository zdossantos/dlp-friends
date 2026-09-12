<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, MessageCircle } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
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
        <Button
            type="button"
            variant="ghost"
            :data-test="`notification-${notification.id}`"
            class="h-auto w-full items-start justify-start gap-3 rounded-none px-4 py-4 text-left hover:bg-muted/60 focus-visible:ring-inset"
            :class="notification.read_at === null ? 'bg-primary/8' : ''"
            :disabled="processing"
            :aria-busy="processing"
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
        </Button>
    </li>
</template>
