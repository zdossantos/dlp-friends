<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { close, index } from '@/routes/admin/conversation-reports';
import { index as members } from '@/routes/admin/members';
import { update as updateBan } from '@/routes/admin/members/ban';
import type { ConversationReport, ModerationPage } from '@/types/moderation';
const props = defineProps<{
    report: ConversationReport;
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

    form.patch(close(props.report.id).url, { preserveScroll: true });
}
function author(id: number): string {
    return id === props.report.reporter.id
        ? props.report.reporter.name
        : props.report.target.name;
}
</script>
<template>
    <Head :title="t('moderation.review_title')" />
    <main class="grid gap-5 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('moderation.review_title')"
            :description="t('moderation.review_description')"
        /><Link :href="index().url">{{ t('moderation.reports_title') }}</Link>
        <section class="grid gap-3 rounded-xl border bg-card p-4">
            <p>{{ t(`moderation.reasons.${report.reason}`) }}</p>
            <p v-if="report.details" class="break-words whitespace-pre-wrap">
                {{ report.details }}
            </p>
            <div class="flex flex-wrap gap-3">
                <Link
                    v-if="report.reporter.id"
                    :href="
                        members({ query: { member: report.reporter.id } }).url
                    "
                    >{{ report.reporter.name }}</Link
                ><Link
                    v-if="report.target.id"
                    :href="members({ query: { member: report.target.id } }).url"
                    >{{ report.target.name }}</Link
                >
            </div>
        </section>
        <section
            class="grid gap-3"
            :aria-label="t('moderation.full_conversation')"
        >
            <article
                v-for="message in messages.data"
                :key="message.id"
                class="rounded-xl border bg-card p-4"
            >
                <p class="font-medium">{{ author(message.author_user_id) }}</p>
                <p class="break-words whitespace-pre-wrap">
                    {{ message.content }}
                </p>
                <time class="text-xs text-muted-foreground">{{
                    formatDate(message.created_at, {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    })
                }}</time>
            </article>
            <nav class="flex gap-3">
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
        <section v-if="report.closed_at" class="rounded-xl border p-4">
            <p>{{ t('moderation.closed') }}</p>
            <p class="whitespace-pre-wrap">{{ report.decision }}</p>
        </section>
        <form
            v-if="
                !report.closed_at || (report.target.id && report.target.can_ban)
            "
            class="grid gap-3"
            @submit.prevent="submit"
        >
            <Label for="close-decision">{{
                t('moderation.decision_reason')
            }}</Label
            ><Textarea
                id="close-decision"
                v-model="form.decision"
                required
                maxlength="1000"
            />
            <InputError
                :message="form.errors.decision || banForm.errors.reason"
            />
            <InputError
                :message="form.errors.confirmed || banForm.errors.confirmed"
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
                    :variant="report.target.banned ? 'outline' : 'destructive'"
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
    </main>
</template>
