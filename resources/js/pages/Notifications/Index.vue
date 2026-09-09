<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import NotificationFilters from '@/components/notifications/NotificationFilters.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { NotificationCategory } from '@/lib/notificationFilters';
import { readAll } from '@/routes/notifications';
import type { NotificationPage } from '@/types/notification';

defineProps<{
    filters: { category: NotificationCategory | null; unread: boolean };
    notifications: NotificationPage;
}>();
const { t } = useTranslations();

function markAllRead(): void {
    router.patch(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('notifications.page.title')" />
    <main
        class="mx-auto flex h-full min-h-0 w-full max-w-2xl flex-col gap-6 overflow-hidden px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <header class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ t('notifications.page.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('notifications.page.description') }}
                </p>
            </div>
            <Button
                v-if="notifications.data.some((item) => item.read_at === null)"
                type="button"
                variant="outline"
                size="sm"
                @click="markAllRead"
            >
                {{ t('notifications.actions.mark_all_read') }}
            </Button>
        </header>

        <NotificationFilters
            :category="filters.category"
            :unread="filters.unread"
        />

        <section
            v-if="notifications.data.length === 0"
            class="rounded-3xl border bg-card p-8 text-center shadow-sm"
        >
            <h2 class="font-semibold">
                {{ t('notifications.page.empty_title') }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('notifications.page.empty_description') }}
            </p>
        </section>
        <section
            v-else
            :aria-label="t('notifications.page.list_label')"
            class="min-h-0 flex-1 overflow-y-auto rounded-3xl border bg-card shadow-sm"
        >
            <ul role="list" class="divide-y">
                <NotificationItem
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :notification="notification"
                />
            </ul>
        </section>

        <nav
            v-if="notifications.links.length > 3"
            :aria-label="t('notifications.page.pagination')"
            class="flex flex-wrap justify-center gap-2 pb-2"
        >
            <Link
                v-for="link in notifications.links"
                :key="link.label"
                :href="link.url ?? ''"
                :aria-disabled="link.url === null"
                :aria-current="link.active ? 'page' : undefined"
                class="rounded-lg border px-3 py-2 text-sm"
                :class="[
                    link.active ? 'bg-primary text-primary-foreground' : '',
                    link.url === null ? 'pointer-events-none opacity-50' : '',
                ]"
            >
                <span v-html="link.label" />
            </Link>
        </nav>
    </main>
</template>
