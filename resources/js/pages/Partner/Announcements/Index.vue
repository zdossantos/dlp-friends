<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Ban,
    CopyPlus,
    ExternalLink,
    Megaphone,
    Pencil,
    Plus,
    Send,
    Trash2,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import {
    cancel,
    create,
    destroy,
    edit,
    revise,
    submit,
} from '@/routes/partner/announcements';
import type { PartnerAnnouncement, PartnerAnnouncementStatus } from '@/types';

defineProps<{
    canCreate: boolean;
    announcements: PartnerAnnouncement[];
}>();

const { formatDate, t } = useTranslations();

const statusKeys: Record<PartnerAnnouncementStatus, TranslationKey> = {
    draft: 'partners.announcements.status.draft',
    pending_approval: 'partners.announcements.status.pending_approval',
    approved: 'partners.announcements.status.approved',
    sending: 'partners.announcements.status.sending',
    sent: 'partners.announcements.status.sent',
    rejected: 'partners.announcements.status.rejected',
    cancelled: 'partners.announcements.status.cancelled',
};
</script>

<template>
    <Head :title="t('partners.announcements.page_title')" />

    <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:py-10">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-primary/10 p-2 text-primary">
                        <Megaphone class="size-6" aria-hidden="true" />
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ t('partners.announcements.title') }}
                    </h1>
                </div>
                <p class="max-w-3xl text-muted-foreground">
                    {{ t('partners.announcements.description') }}
                </p>
            </div>
            <Button v-if="canCreate" as-child class="min-h-11">
                <Link :href="create().url">
                    <Plus aria-hidden="true" />
                    {{ t('partners.announcements.create') }}
                </Link>
            </Button>
        </header>

        <p
            v-if="!canCreate"
            class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
        >
            {{ t('partners.announcements.profile_required') }}
        </p>

        <p
            v-if="announcements.length === 0"
            class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
        >
            {{ t('partners.announcements.empty') }}
        </p>

        <section v-else class="grid gap-4 lg:grid-cols-2">
            <Card v-for="announcement in announcements" :key="announcement.id">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0 space-y-1">
                            <CardTitle>{{ announcement.title }}</CardTitle>
                            <CardDescription class="space-x-2">
                                <span v-if="announcement.submittedAt">
                                    {{
                                        t(
                                            'partners.announcements.submitted_at',
                                            {
                                                date: formatDate(
                                                    announcement.submittedAt,
                                                    { dateStyle: 'medium' },
                                                ),
                                            },
                                        )
                                    }}
                                </span>
                                <span v-if="announcement.decidedAt">
                                    {{
                                        t('partners.announcements.decided_at', {
                                            date: formatDate(
                                                announcement.decidedAt,
                                                { dateStyle: 'medium' },
                                            ),
                                        })
                                    }}
                                </span>
                            </CardDescription>
                        </div>
                        <Badge
                            :variant="
                                announcement.status === 'rejected'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                        >
                            {{ t(statusKeys[announcement.status]) }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <p class="text-sm leading-6 whitespace-pre-line">
                        {{ announcement.content }}
                    </p>
                    <a
                        :href="announcement.destinationUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-medium break-all text-primary underline underline-offset-4"
                    >
                        {{ announcement.destinationUrl }}
                        <ExternalLink
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </a>
                    <p
                        v-if="announcement.rejectionReason"
                        class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {{
                            t('partners.announcements.rejection_reason', {
                                reason: announcement.rejectionReason,
                            })
                        }}
                    </p>

                    <div class="flex flex-wrap gap-2 border-t pt-4">
                        <Form
                            v-if="announcement.canRevise"
                            v-bind="revise.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                class="min-h-11"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <CopyPlus v-else aria-hidden="true" />
                                {{ t('partners.announcements.revise') }}
                            </Button>
                            <InputError :message="errors.announcement" />
                            <InputError :message="errors.destination_url" />
                        </Form>
                        <Button
                            v-if="announcement.canEdit"
                            as-child
                            variant="outline"
                            class="min-h-11"
                        >
                            <Link :href="edit(announcement.id).url">
                                <Pencil aria-hidden="true" />
                                {{ t('partners.announcements.edit') }}
                            </Link>
                        </Button>
                        <Form
                            v-if="announcement.canSubmit"
                            v-bind="submit.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                class="min-h-11"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <Send v-else aria-hidden="true" />
                                {{ t('partners.announcements.submit') }}
                            </Button>
                            <InputError :message="errors.destination_url" />
                            <InputError :message="errors.announcement" />
                        </Form>
                        <div
                            v-else-if="
                                announcement.status === 'draft' &&
                                announcement.nextSubmissionAt
                            "
                            class="space-y-2"
                        >
                            <Button
                                type="button"
                                class="min-h-11"
                                :data-test="`partner-announcement-cooldown-submit-${announcement.id}`"
                                disabled
                            >
                                <Send aria-hidden="true" />
                                {{ t('partners.announcements.submit') }}
                            </Button>
                            <p
                                class="max-w-sm text-sm text-destructive"
                                role="alert"
                            >
                                {{
                                    t(
                                        'partners.announcements.errors.cooldown',
                                        {
                                            date: formatDate(
                                                announcement.nextSubmissionAt,
                                                {
                                                    dateStyle: 'full',
                                                    timeStyle: 'short',
                                                    timeZone: 'Europe/Paris',
                                                },
                                            ),
                                        },
                                    )
                                }}
                            </p>
                        </div>
                        <Form
                            v-if="announcement.canCancel"
                            v-bind="cancel.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                class="min-h-11"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <Ban v-else aria-hidden="true" />
                                {{ t('partners.announcements.cancel') }}
                            </Button>
                            <InputError :message="errors.decision" />
                        </Form>
                        <Form
                            v-if="announcement.canEdit"
                            v-bind="destroy.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                class="min-h-11"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <Trash2 v-else aria-hidden="true" />
                                {{ t('partners.announcements.delete') }}
                            </Button>
                            <InputError :message="errors.announcement" />
                        </Form>
                    </div>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
