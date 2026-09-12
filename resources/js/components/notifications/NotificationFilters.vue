<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/composables/useTranslations';
import { applyNotificationFilters } from '@/lib/notificationFilters';
import type { NotificationCategory } from '@/lib/notificationFilters';
import { index as notificationsIndex } from '@/routes/notifications';

defineProps<{
    category: NotificationCategory | null;
    unread: boolean;
}>();
const { t } = useTranslations();

function update(category: NotificationCategory | null, unread: boolean): void {
    const query = applyNotificationFilters(category, unread);
    const url = `${notificationsIndex().url}${query.size > 0 ? `?${query}` : ''}`;

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <div class="space-y-3" :aria-label="t('notifications.filters.label')">
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="filter in [
                    { value: null, label: t('notifications.filters.all') },
                    {
                        value: 'conversations' as const,
                        label: t('notifications.filters.conversations'),
                    },
                    {
                        value: 'events' as const,
                        label: t('notifications.filters.events'),
                    },
                ]"
                :key="filter.value ?? 'all'"
                type="button"
                :data-test="`notification-filter-${filter.value ?? 'all'}`"
                :aria-pressed="category === filter.value"
                variant="outline"
                class="rounded-full px-4"
                :class="
                    category === filter.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card hover:bg-muted'
                "
                @click="update(filter.value, unread)"
            >
                {{ filter.label }}
            </Button>
        </div>
        <label class="flex w-fit items-center gap-2 text-sm font-medium">
            <Checkbox
                data-test="notification-filter-unread"
                :model-value="unread"
                @update:model-value="update(category, $event === true)"
            />
            {{ t('notifications.filters.unread_only') }}
        </label>
    </div>
</template>
