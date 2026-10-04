<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import SetMemberBanDialog from '@/components/admin/SetMemberBanDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { close, index } from '@/routes/admin/conversation-reports';
import { index as members } from '@/routes/admin/members';
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
const form = useForm({ decision: '', confirmed: true });
function submit(): void {
    form.patch(close(props.report.id).url);
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
                ><SetMemberBanDialog
                    v-if="report.target.id && report.target.can_ban"
                    :member-id="report.target.id"
                    :banned="report.target.banned"
                />
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
        <form v-else class="grid gap-3" @submit.prevent="submit">
            <Label for="close-decision">{{
                t('moderation.decision_reason')
            }}</Label
            ><Textarea
                id="close-decision"
                v-model="form.decision"
                required
                maxlength="1000"
            /><InputError :message="form.errors.decision" /><InputError
                :message="form.errors.confirmed"
            /><Button
                data-test="close-report"
                :disabled="form.processing"
                type="submit"
                >{{ t('moderation.close_confirm') }}</Button
            >
        </form>
    </main>
</template>
