<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { decide } from '@/routes/admin/partner-profile-revisions';

export type ModeratedPartnerRevision = {
    id: number;
    profileId: number;
    nameFr: string;
    nameEn: string;
    descriptionFr: string;
    descriptionEn: string;
    imageUrl: string | null;
    status: string;
    submittedAt: string | null;
    rejectionReason: string | null;
};

defineProps<{ revision: ModeratedPartnerRevision }>();

const { formatDate, t } = useTranslations();
</script>

<template>
    <Card data-test="partner-moderation-card">
        <CardHeader>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <img
                    v-if="revision.imageUrl"
                    :src="revision.imageUrl"
                    :alt="revision.nameFr"
                    class="aspect-video w-full rounded-xl object-cover sm:w-56"
                />
                <div class="min-w-0 space-y-1">
                    <CardTitle>{{ revision.nameFr }}</CardTitle>
                    <CardDescription>{{ revision.nameEn }}</CardDescription>
                    <p
                        v-if="revision.submittedAt"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            t('administration.partners.submitted_at', {
                                date: formatDate(revision.submittedAt, {
                                    dateStyle: 'medium',
                                }),
                            })
                        }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-6">
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border p-4">
                    <h3 class="font-semibold">
                        {{ t('administration.partners.french_content') }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        {{ revision.descriptionFr }}
                    </p>
                </section>
                <section class="rounded-xl border p-4" lang="en">
                    <h3 class="font-semibold">
                        {{ t('administration.partners.english_content') }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        {{ revision.descriptionEn }}
                    </p>
                </section>
            </div>

            <div class="grid gap-4 border-t pt-5 lg:grid-cols-2">
                <Form
                    v-bind="decide.form(revision.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="space-y-2"
                >
                    <input type="hidden" name="decision" value="approve" />
                    <Button
                        type="submit"
                        class="min-h-11 w-full"
                        data-test="approve-partner-profile"
                        :disabled="processing"
                        :aria-busy="processing ? 'true' : undefined"
                    >
                        <Spinner v-if="processing" />
                        <CircleCheck v-else aria-hidden="true" />
                        {{
                            processing
                                ? t('administration.partners.approving')
                                : t('administration.partners.approve')
                        }}
                    </Button>
                    <InputError :message="errors.decision" />
                </Form>

                <Form
                    v-bind="decide.form(revision.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="space-y-2"
                >
                    <input type="hidden" name="decision" value="reject" />
                    <Label :for="`rejection-reason-${revision.id}`">
                        {{ t('administration.partners.rejection_reason') }}
                    </Label>
                    <Textarea
                        :id="`rejection-reason-${revision.id}`"
                        name="rejection_reason"
                        maxlength="500"
                        :placeholder="
                            t(
                                'administration.partners.rejection_reason_placeholder',
                            )
                        "
                        :aria-invalid="
                            errors.rejection_reason ? 'true' : undefined
                        "
                    />
                    <InputError :message="errors.rejection_reason" />
                    <InputError :message="errors.decision" />
                    <Button
                        type="submit"
                        variant="destructive"
                        class="min-h-11 w-full"
                        data-test="reject-partner-profile"
                        :disabled="processing"
                        :aria-busy="processing ? 'true' : undefined"
                    >
                        <Spinner v-if="processing" />
                        <CircleX v-else aria-hidden="true" />
                        {{
                            processing
                                ? t('administration.partners.rejecting')
                                : t('administration.partners.reject')
                        }}
                    </Button>
                </Form>
            </div>
        </CardContent>
    </Card>
</template>
