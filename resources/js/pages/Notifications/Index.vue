<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Settings } from '@lucide/vue';
import { ref } from 'vue';
import NotificationFilters from '@/components/notifications/NotificationFilters.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import SeasonalDecorations from '@/components/seasonal/SeasonalDecorations.vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import type { NotificationCategory } from '@/lib/notificationFilters';
import { edit as editNotificationSettings } from '@/routes/notification-preferences';
import { readAll } from '@/routes/notifications';
import type {
    MemberNotification,
    NotificationPage,
} from '@/types/notification';

const props = defineProps<{
    canViewAdministrationNotifications: boolean;
    indexUrl: string;
    filters: { category: NotificationCategory | null; unread: boolean };
    notifications: NotificationPage;
}>();
const { t, formatDate } = useTranslations();
const openNotificationId = ref<string | null>(null);
const selectedAnnouncement = ref<MemberNotification | null>(null);
const announcementOpen = ref(false);
const notificationHeading = ref<HTMLElement | null>(null);
const { isDesktop, Modal } = useResponsiveModal();

function openAnnouncement(notification: MemberNotification): void {
    selectedAnnouncement.value = notification;
    announcementOpen.value = true;
}

function focusAnnouncement(event: Event): void {
    event.preventDefault();
    (event.currentTarget as HTMLElement)?.focus({ preventScroll: true });
}

function restoreAnnouncementFocus(event: Event): void {
    event.preventDefault();
    const trigger = document.querySelector<HTMLElement>(
        `[data-test="notification-${selectedAnnouncement.value?.id}"]`,
    );
    (trigger ?? notificationHeading.value)?.focus({ preventScroll: true });
}

function markAllRead(): void {
    router.patch(
        readAll().url,
        { context: props.indexUrl.startsWith('/admin/') ? 'admin' : null },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('notifications.page.title')" />
    <main
        class="mx-auto flex h-full min-h-0 w-full max-w-2xl min-w-0 flex-col gap-6 overflow-hidden px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <header
            class="flex min-w-0 flex-wrap items-start justify-between gap-4"
        >
            <div class="min-w-0 space-y-1">
                <h1
                    ref="notificationHeading"
                    data-test="notification-heading"
                    tabindex="-1"
                    class="text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {{ t('notifications.page.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('notifications.page.description') }}
                </p>
            </div>
            <div class="flex max-w-full shrink-0 flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="editNotificationSettings()"
                        data-test="manage-notification-settings"
                    >
                        <Settings class="size-4" aria-hidden="true" />
                        <span class="sr-only sm:not-sr-only">
                            {{ t('notifications.actions.manage_settings') }}
                        </span>
                    </Link>
                </Button>
                <Button
                    v-if="
                        notifications.data.some((item) => item.read_at === null)
                    "
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="markAllRead"
                >
                    {{ t('notifications.actions.mark_all_read') }}
                </Button>
            </div>
        </header>

        <NotificationFilters
            :index-url="indexUrl"
            :category="filters.category"
            :unread="filters.unread"
            :can-view-administration-notifications="
                canViewAdministrationNotifications
            "
        />

        <section
            v-if="notifications.data.length === 0"
            class="relative isolate overflow-hidden rounded-3xl border bg-card p-8 text-center shadow-sm"
        >
            <SeasonalDecorations placement="panel" />
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
            class="relative isolate min-h-0 max-w-full min-w-0 flex-1 overflow-x-hidden overflow-y-auto rounded-3xl border bg-card shadow-sm"
        >
            <SeasonalDecorations placement="panel" />
            <ul role="list" class="relative z-10 max-w-full min-w-0 divide-y">
                <NotificationItem
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :notification="notification"
                    :open="openNotificationId === notification.id"
                    @open-announcement="openAnnouncement"
                    @request-open="
                        openNotificationId = $event ? notification.id : null
                    "
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

    <component :is="Modal.Root" v-model:open="announcementOpen">
        <component
            :is="Modal.Content"
            data-test="partner-announcement-panel"
            :data-panel-mode="isDesktop ? 'dialog' : 'drawer'"
            :aria-describedby="undefined"
            tabindex="-1"
            class="flex max-h-[85svh] min-w-0 flex-col gap-0 overflow-hidden bg-card p-0 text-card-foreground sm:max-w-2xl"
            @open-auto-focus="focusAnnouncement"
            @close-auto-focus="restoreAnnouncementFocus"
        >
            <template v-if="selectedAnnouncement">
                <component
                    :is="Modal.Header"
                    class="shrink-0 px-5 py-5 text-left sm:px-6 sm:pr-12"
                >
                    <component :is="Modal.Title" class="min-w-0 break-words">
                        {{
                            t(
                                selectedAnnouncement.translation_key,
                                selectedAnnouncement.parameters,
                            )
                        }}
                    </component>
                    <time
                        v-if="selectedAnnouncement.created_at"
                        :datetime="selectedAnnouncement.created_at"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            formatDate(selectedAnnouncement.created_at, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })
                        }}
                    </time>
                </component>
                <div
                    role="region"
                    :aria-label="
                        t(
                            selectedAnnouncement.translation_key,
                            selectedAnnouncement.parameters,
                        )
                    "
                    tabindex="0"
                    class="min-h-0 min-w-0 flex-1 overflow-y-auto px-5 pb-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:px-6"
                >
                    <p
                        data-test="partner-announcement-content"
                        class="text-base leading-relaxed break-words whitespace-pre-wrap"
                    >
                        {{ selectedAnnouncement.content }}
                    </p>
                </div>
                <component
                    :is="Modal.Footer"
                    class="shrink-0 gap-3 border-t px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6"
                >
                    <component :is="Modal.Close" as-child>
                        <Button
                            variant="outline"
                            class="min-h-11"
                            data-test="partner-announcement-close"
                            >{{ t('common.actions.close') }}</Button
                        >
                    </component>
                    <Button as-child class="min-h-11">
                        <a
                            :href="selectedAnnouncement.target_url"
                            data-test="partner-announcement-link"
                        >
                            <ExternalLink class="size-4" aria-hidden="true" />
                            {{ t('notifications.actions.open_partner_link') }}
                        </a>
                    </Button>
                </component>
            </template>
        </component>
    </component>
</template>
