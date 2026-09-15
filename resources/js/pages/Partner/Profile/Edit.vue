<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, CircleCheck, Clock3 } from '@lucide/vue';
import { computed } from 'vue';
import PartnerProfileForm from '@/components/partners/PartnerProfileForm.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { TranslationKey } from '@/composables/useTranslations';
import type { PartnerRevision, PartnerRevisionStatus } from '@/types';

const props = defineProps<{
    publishedRevision: PartnerRevision | null;
    draft: PartnerRevision | null;
    latestSubmission: PartnerRevision | null;
}>();

const { t, formatDate } = useTranslations();
const initialRevision = computed(
    () => props.draft ?? props.latestSubmission ?? props.publishedRevision,
);

const statusKeys: Record<PartnerRevisionStatus, TranslationKey> = {
    draft: 'partners.profile.status.draft',
    pending_approval: 'partners.profile.status.pending_approval',
    approved: 'partners.profile.status.approved',
    rejected: 'partners.profile.status.rejected',
};

function statusLabel(status: PartnerRevisionStatus): string {
    return t(statusKeys[status]);
}
</script>

<template>
    <Head :title="t('partners.profile.page_title')" />

    <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:py-10">
        <header class="space-y-2">
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-primary/10 p-2 text-primary">
                    <Building2 class="size-6" aria-hidden="true" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ t('partners.profile.title') }}
                </h1>
            </div>
            <p class="max-w-3xl text-muted-foreground">
                {{ t('partners.profile.description') }}
            </p>
        </header>

        <section class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1.5">
                            <CardTitle>
                                {{ t('partners.profile.published_title') }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t('partners.profile.published_description')
                                }}
                            </CardDescription>
                        </div>
                        <CircleCheck
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                    </div>
                </CardHeader>
                <CardContent v-if="publishedRevision" class="space-y-3">
                    <img
                        v-if="publishedRevision.imageUrl"
                        :src="publishedRevision.imageUrl"
                        :alt="publishedRevision.nameFr"
                        class="aspect-video w-full rounded-xl object-cover"
                    />
                    <div>
                        <p class="font-semibold">
                            {{ publishedRevision.nameFr }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ publishedRevision.nameEn }}
                        </p>
                    </div>
                </CardContent>
                <CardContent v-else>
                    <p class="text-sm text-muted-foreground">
                        {{ t('partners.profile.not_published') }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1.5">
                            <CardTitle>
                                {{
                                    t(
                                        'partners.profile.latest_submission_title',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'partners.profile.latest_submission_description',
                                    )
                                }}
                            </CardDescription>
                        </div>
                        <Clock3
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                    </div>
                </CardHeader>
                <CardContent v-if="latestSubmission" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            :variant="
                                latestSubmission.status === 'rejected'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                        >
                            {{ statusLabel(latestSubmission.status) }}
                        </Badge>
                        <p
                            v-if="latestSubmission.submittedAt"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                t('partners.profile.submitted_at', {
                                    date: formatDate(
                                        latestSubmission.submittedAt,
                                        {
                                            dateStyle: 'medium',
                                        },
                                    ),
                                })
                            }}
                        </p>
                    </div>
                    <p
                        v-if="latestSubmission.rejectionReason"
                        class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {{
                            t('partners.profile.rejection_reason', {
                                reason: latestSubmission.rejectionReason,
                            })
                        }}
                    </p>
                </CardContent>
                <CardContent v-else>
                    <p class="text-sm text-muted-foreground">
                        {{ t('partners.profile.form.submit_hint') }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <PartnerProfileForm
            :initial-revision="initialRevision"
            :has-draft="draft !== null"
        />
    </main>
</template>
