<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { close } from '@/routes/admin/conversation-reports';
import { index as members } from '@/routes/admin/members';
import { update as updateBan } from '@/routes/admin/members/ban';
import type { ConversationReport, ModerationPage } from '@/types/moderation';
const props = defineProps<{
    report: ConversationReport;
    backHref: string;
    publicProfileUrls: { reporter: string | null; target: string | null };
    context: Record<string, string | number | boolean>;
    messages: ModerationPage<{
        id: number;
        author_user_id: number;
        content: string;
        created_at: string;
    }>;
}>();
const { t, formatDate } = useTranslations();
const form = useForm({
    decision: props.report.decision ?? '',
    confirmed: true,
});
const banForm = useForm({ reason: '', banned: false, confirmed: true });
function submit(event: SubmitEvent): void {
    form.clearErrors();
    banForm.clearErrors();

    if (event.submitter?.getAttribute('value') === 'ban') {
        if (!props.report.target.id || !props.report.target.can_ban) {
            return;
        }

        banForm.reason = form.decision;
        banForm.banned = !props.report.target.banned;
        banForm.patch(updateBan(props.report.target.id).url, {
            preserveScroll: true,
        });

        return;
    }

    form.patch(close(props.report.id, { query: props.context }).url, {
        preserveScroll: true,
    });
}
function author(id: number): string {
    return id === props.report.reporter.id
        ? props.report.reporter.name
        : props.report.target.name;
}
</script>
<template>
    <Head :title="t('moderation.review_title')" />
    <main class="grid min-w-0 gap-6 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('moderation.review_title')"
            :description="t('moderation.review_description')"
        />
        <div class="flex flex-wrap items-center gap-3">
            <Button as-child variant="outline"
                ><Link :href="backHref" data-test="reports-back">{{
                    t('moderation.back_to_list')
                }}</Link></Button
            >
            <Badge :variant="report.closed_at ? 'secondary' : 'default'">{{
                t(
                    report.closed_at
                        ? 'moderation.state_closed'
                        : 'moderation.state_open',
                )
            }}</Badge>
            <time
                :datetime="report.created_at"
                class="text-sm text-muted-foreground"
                >{{
                    formatDate(report.created_at, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })
                }}</time
            >
        </div>
        <section
            class="grid gap-4 rounded-xl border bg-card p-4 sm:p-6"
            aria-labelledby="report-context"
        >
            <h2 id="report-context" class="text-lg font-semibold">
                {{ t('moderation.report_context') }}
            </h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="person in [
                        { role: 'reporter' as const, ...report.reporter },
                        { role: 'target' as const, ...report.target },
                    ]"
                    :key="person.role"
                    class="min-w-0"
                >
                    <dt class="mb-1 text-sm text-muted-foreground">
                        {{ t(`moderation.${person.role}`) }}
                    </dt>
                    <dd class="font-medium break-words">
                        <span>{{ person.name }}</span>
                        <div v-if="person.id" class="mt-2 flex flex-wrap gap-2">
                            <Button
                                v-if="publicProfileUrls[person.role]"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link
                                    :href="publicProfileUrls[person.role]!"
                                    :data-test="`report-${person.role}-profile`"
                                    >{{
                                        t('moderation.view_public_profile')
                                    }}</Link
                                >
                            </Button>
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        members({
                                            query: { member: person.id },
                                        }).url
                                    "
                                    :data-test="`report-${person.role}-admin`"
                                    >{{
                                        t('moderation.administrative_record')
                                    }}</Link
                                >
                            </Button>
                        </div>
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="mb-1 text-sm text-muted-foreground">
                        {{ t('moderation.reason') }}
                    </dt>
                    <dd class="font-medium">
                        {{ t(`moderation.reasons.${report.reason}`) }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="report.details"
                class="max-w-prose break-words whitespace-pre-wrap"
            >
                {{ report.details }}
            </p>
            <div class="flex flex-wrap gap-2 lg:hidden">
                <Button as-child variant="outline"
                    ><a href="#reported-conversation">{{
                        t('moderation.full_conversation')
                    }}</a></Button
                >
                <Button as-child variant="outline"
                    ><a href="#report-decision">{{
                        t('moderation.processing')
                    }}</a></Button
                >
            </div>
        </section>
        <div
            class="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <section
                id="reported-conversation"
                class="grid min-w-0 scroll-mt-4 gap-3"
                aria-labelledby="conversation-title"
            >
                <h2 id="conversation-title" class="text-lg font-semibold">
                    {{ t('moderation.full_conversation') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('moderation.review_description') }}
                </p>
                <p
                    v-if="messages.data.length === 0"
                    class="rounded-xl border border-dashed p-6 text-muted-foreground"
                >
                    {{ t('moderation.no_messages') }}
                </p>
                <article
                    v-for="message in messages.data"
                    :key="message.id"
                    class="min-w-0 rounded-xl border bg-card p-4"
                >
                    <p class="font-medium">
                        {{ author(message.author_user_id) }}
                    </p>
                    <p class="break-words whitespace-pre-wrap">
                        {{ message.content }}
                    </p>
                    <time
                        :datetime="message.created_at"
                        class="text-xs text-muted-foreground"
                        >{{
                            formatDate(message.created_at, {
                                dateStyle: 'short',
                                timeStyle: 'short',
                            })
                        }}</time
                    >
                </article>
                <nav
                    v-if="messages.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-3"
                    :aria-label="t('moderation.message_pagination')"
                >
                    <Link
                        v-if="messages.prev_page_url"
                        :href="messages.prev_page_url"
                        >{{ t('moderation.previous') }}</Link
                    ><Link
                        v-if="messages.next_page_url"
                        :href="messages.next_page_url"
                        >{{ t('moderation.next') }}</Link
                    >
                </nav>
            </section>
            <aside
                id="report-decision"
                class="grid min-w-0 scroll-mt-4 gap-4 rounded-xl border bg-card p-4 sm:p-6 lg:sticky lg:top-4"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('moderation.processing') }}
                </h2>
                <section v-if="report.closed_at" class="rounded-xl border p-4">
                    <h3 class="font-semibold">{{ t('moderation.closure') }}</h3>
                    <p class="my-2 text-sm text-muted-foreground">
                        {{
                            t('moderation.closed_date', {
                                date: formatDate(report.closed_at, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                }),
                            })
                        }}
                    </p>
                    <p class="break-words whitespace-pre-wrap">
                        {{ report.decision }}
                    </p>
                </section>
                <form
                    v-if="
                        !report.closed_at ||
                        (report.target.id && report.target.can_ban)
                    "
                    class="grid gap-3"
                    @submit.prevent="submit"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('moderation.confirm_description') }}
                    </p>
                    <Label for="close-decision">{{
                        t('moderation.decision_reason')
                    }}</Label
                    ><Textarea
                        id="close-decision"
                        v-model="form.decision"
                        required
                        maxlength="1000"
                        :disabled="form.processing || banForm.processing"
                        :aria-invalid="
                            Boolean(
                                form.errors.decision || banForm.errors.reason,
                            )
                        "
                        :aria-describedby="
                            [
                                form.errors.decision || banForm.errors.reason
                                    ? 'decision-error'
                                    : null,
                                report.target.id && report.target.can_ban
                                    ? 'report-ban-warning'
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' ') || undefined
                        "
                    />
                    <InputError
                        id="decision-error"
                        :message="form.errors.decision || banForm.errors.reason"
                    />
                    <InputError
                        :message="
                            form.errors.confirmed || banForm.errors.confirmed
                        "
                    />
                    <InputError :message="banForm.errors.banned" />
                    <p
                        v-if="report.target.id && report.target.can_ban"
                        id="report-ban-warning"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            report.target.banned
                                ? t('moderation.unban_warning')
                                : t('moderation.ban_warning')
                        }}
                    </p>
                    <p
                        v-if="form.processing || banForm.processing"
                        role="status"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('moderation.loading') }}
                    </p>
                    <div class="flex gap-3">
                        <Button
                            v-if="!report.closed_at"
                            data-test="close-report"
                            class="h-auto min-h-11 flex-1 px-3 py-2 whitespace-normal"
                            :disabled="form.processing || banForm.processing"
                            type="submit"
                            value="close"
                            >{{ t('moderation.close_confirm') }}</Button
                        >
                        <Button
                            v-if="report.target.id && report.target.can_ban"
                            data-test="review-member-ban"
                            class="h-auto min-h-11 flex-1 px-3 py-2 whitespace-normal"
                            :variant="
                                report.target.banned ? 'outline' : 'destructive'
                            "
                            :disabled="form.processing || banForm.processing"
                            aria-describedby="report-ban-warning"
                            type="submit"
                            value="ban"
                            >{{
                                report.target.banned
                                    ? t('moderation.unban')
                                    : t('moderation.ban')
                            }}</Button
                        >
                    </div>
                </form>
            </aside>
        </div>
    </main>
</template>
