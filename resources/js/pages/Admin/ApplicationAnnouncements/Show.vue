<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index, retry, send } from '@/routes/admin/application-announcements';
import type { PaginationLink } from '@/types/notification';

defineProps<{
    announcement: {
        id: number;
        title_fr: string;
        title_en: string;
        content_fr: string;
        content_en: string;
        destination_url: string | null;
        is_release: boolean;
        send_push: boolean;
        audience: string;
        sent_at: string | null;
        counts: Record<string, number>;
    };
    recipients: {
        data: { id: number; label: string; email: string; status: string }[];
        links: PaginationLink[];
        total: number;
    };
}>();
const { t } = useTranslations();
const busy = ref(false);
function submit(url: string): void {
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <Head :title="t('application_announcements.review')" />
    <main class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
        <Button variant="outline" as-child
            ><Link :href="index()">{{
                t('application_announcements.back')
            }}</Link></Button
        >
        <h1 class="text-2xl font-semibold">
            {{
                t(
                    announcement.sent_at
                        ? 'application_announcements.sent'
                        : 'application_announcements.review',
                )
            }}
        </h1>
        <section
            v-for="language in ['fr', 'en'] as const"
            :key="language"
            class="space-y-3 rounded-2xl border bg-card p-4 sm:p-6"
        >
            <p class="text-sm text-muted-foreground">
                {{ t(`application_announcements.languages.${language}`) }}
            </p>
            <h2 class="text-xl font-semibold break-words">
                {{ announcement[`title_${language}`] }}
            </h2>
            <p class="break-words whitespace-pre-wrap">
                {{ announcement[`content_${language}`] }}
            </p>
            <a
                v-if="announcement.is_release || announcement.destination_url"
                :href="
                    announcement.is_release
                        ? language === 'fr'
                            ? '/fr/nouveautes'
                            : '/en/whats-new'
                        : announcement.destination_url!
                "
                class="inline-block break-all text-primary underline"
                >{{
                    t(
                        announcement.is_release
                            ? 'application_announcements.changelog'
                            : 'application_announcements.link',
                    )
                }}</a
            >
        </section>
        <section class="space-y-3 rounded-2xl border bg-card p-4 sm:p-6">
            <h2 class="font-semibold">
                {{ t('application_announcements.fields.audience') }}
            </h2>
            <p>
                {{
                    t(
                        `application_announcements.audiences.${announcement.audience}`,
                    )
                }}
                · {{ recipients.total }}
            </p>
            <p>
                {{
                    t(
                        announcement.send_push
                            ? 'application_announcements.push_yes'
                            : 'application_announcements.push_no',
                    )
                }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ t('application_announcements.frozen_help') }}
            </p>
            <dl
                v-if="announcement.sent_at"
                class="grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
                <div
                    v-for="(count, status) in announcement.counts"
                    :key="status"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ t(`application_announcements.statuses.${status}`) }}
                    </dt>
                    <dd class="font-semibold">{{ count }}</dd>
                </div>
            </dl>
            <ul class="divide-y">
                <li
                    v-for="recipient in recipients.data"
                    :key="recipient.id"
                    class="flex flex-wrap justify-between gap-2 py-3 text-sm break-all"
                >
                    <span>{{ recipient.label }} · {{ recipient.email }}</span
                    ><span>{{
                        t(
                            `application_announcements.statuses.${recipient.status}`,
                        )
                    }}</span>
                </li>
            </ul>
            <nav
                class="flex flex-wrap gap-2"
                :aria-label="t('application_announcements.pagination')"
            >
                <Button
                    v-for="(link, position) in recipients.links"
                    :key="position"
                    type="button"
                    variant="outline"
                    :disabled="!link.url || link.active"
                    :aria-current="link.active ? 'page' : undefined"
                    @click="link.url && router.get(link.url)"
                    >{{
                        position === 0
                            ? t('application_announcements.previous')
                            : position === recipients.links.length - 1
                              ? t('application_announcements.next')
                              : link.label
                    }}</Button
                >
            </nav>
            <Button
                v-if="!announcement.sent_at"
                :disabled="busy || recipients.total === 0"
                :aria-busy="busy"
                @click="submit(send(announcement.id).url)"
                >{{ t('application_announcements.confirm') }}</Button
            >
            <Button
                v-else
                variant="outline"
                :disabled="busy"
                :aria-busy="busy"
                @click="submit(retry(announcement.id).url)"
                >{{ t('application_announcements.retry') }}</Button
            >
            <Button
                v-if="announcement.sent_at"
                variant="outline"
                :disabled="busy"
                @click="router.reload({ only: ['announcement', 'recipients'] })"
                >{{ t('application_announcements.refresh') }}</Button
            >
        </section>
    </main>
</template>
