<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Ban,
    CircleCheck,
    CircleX,
    ExternalLink,
    Megaphone,
    Save,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { decide } from '@/routes/admin/partner-announcements';
import { update as updateSettings } from '@/routes/admin/partner-settings';

type ModeratedAnnouncement = {
    id: number;
    partnerName: string | null;
    title: string;
    content: string;
    destinationUrl: string;
    submittedAt: string | null;
};

defineProps<{
    announcements: ModeratedAnnouncement[];
    cooldownDays: number;
}>();

const { formatDate, t } = useTranslations();
</script>

<template>
    <Head :title="t('administration.partner_announcements.page_title')" />

    <main class="flex flex-1 flex-col gap-8 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <div class="mt-1 flex items-center gap-3">
                <Megaphone class="size-7 text-primary" aria-hidden="true" />
                <h1 class="text-3xl font-semibold tracking-tight">
                    {{ t('administration.partner_announcements.title') }}
                </h1>
            </div>
            <p class="mt-2 max-w-3xl text-muted-foreground">
                {{ t('administration.partner_announcements.description') }}
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>
                    {{
                        t('administration.partner_announcements.cooldown_title')
                    }}
                </CardTitle>
                <CardDescription>
                    {{
                        t(
                            'administration.partner_announcements.cooldown_description',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="updateSettings.form()"
                    :options="{ preserveScroll: true }"
                    class="flex max-w-xl flex-col gap-3 sm:flex-row sm:items-end"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="cooldown_days">
                            {{
                                t(
                                    'administration.partner_announcements.cooldown_label',
                                )
                            }}
                        </Label>
                        <Input
                            id="cooldown_days"
                            name="cooldown_days"
                            type="number"
                            min="1"
                            max="365"
                            required
                            class="h-11"
                            :default-value="cooldownDays"
                            :aria-invalid="
                                errors.cooldown_days ? 'true' : undefined
                            "
                        />
                        <InputError :message="errors.cooldown_days" />
                    </div>
                    <Button
                        type="submit"
                        class="min-h-11"
                        :disabled="processing"
                        :aria-busy="processing ? 'true' : undefined"
                    >
                        <Spinner v-if="processing" />
                        <Save v-else aria-hidden="true" />
                        {{
                            processing
                                ? t(
                                      'administration.partner_announcements.cooldown_saving',
                                  )
                                : t(
                                      'administration.partner_announcements.cooldown_save',
                                  )
                        }}
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <section
            aria-labelledby="pending-announcements-title"
            class="space-y-4"
        >
            <div>
                <h2
                    id="pending-announcements-title"
                    class="text-2xl font-semibold"
                >
                    {{
                        t('administration.partner_announcements.pending_title')
                    }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        t(
                            'administration.partner_announcements.pending_description',
                        )
                    }}
                </p>
            </div>

            <p
                v-if="announcements.length === 0"
                class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
            >
                {{ t('administration.partner_announcements.pending_empty') }}
            </p>

            <Card
                v-for="announcement in announcements"
                :key="announcement.id"
                data-test="partner-announcement-moderation-card"
            >
                <CardHeader>
                    <CardTitle>{{ announcement.title }}</CardTitle>
                    <CardDescription>
                        <span v-if="announcement.partnerName">
                            {{ announcement.partnerName }} ·
                        </span>
                        <span v-if="announcement.submittedAt">
                            {{
                                t(
                                    'administration.partner_announcements.submitted_at',
                                    {
                                        date: formatDate(
                                            announcement.submittedAt,
                                            { dateStyle: 'medium' },
                                        ),
                                    },
                                )
                            }}
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
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

                    <div class="grid gap-4 border-t pt-5 lg:grid-cols-3">
                        <Form
                            v-bind="decide.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            class="space-y-2"
                            v-slot="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="decision"
                                value="approve"
                            />
                            <Button
                                type="submit"
                                class="min-h-11 w-full"
                                data-test="approve-partner-announcement"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <CircleCheck v-else aria-hidden="true" />
                                {{
                                    processing
                                        ? t(
                                              'administration.partner_announcements.approving',
                                          )
                                        : t(
                                              'administration.partner_announcements.approve',
                                          )
                                }}
                            </Button>
                            <InputError :message="errors.destination_url" />
                            <InputError :message="errors.decision" />
                        </Form>

                        <Form
                            v-bind="decide.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            class="space-y-2"
                            v-slot="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="decision"
                                value="reject"
                            />
                            <Label
                                :for="`announcement-reason-${announcement.id}`"
                            >
                                {{
                                    t(
                                        'administration.partner_announcements.rejection_reason',
                                    )
                                }}
                            </Label>
                            <Textarea
                                :id="`announcement-reason-${announcement.id}`"
                                name="rejection_reason"
                                maxlength="500"
                                :placeholder="
                                    t(
                                        'administration.partner_announcements.rejection_reason_placeholder',
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
                                data-test="reject-partner-announcement"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <CircleX v-else aria-hidden="true" />
                                {{
                                    processing
                                        ? t(
                                              'administration.partner_announcements.rejecting',
                                          )
                                        : t(
                                              'administration.partner_announcements.reject',
                                          )
                                }}
                            </Button>
                        </Form>

                        <Form
                            v-bind="decide.form(announcement.id)"
                            :options="{ preserveScroll: true }"
                            class="space-y-2"
                            v-slot="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="decision"
                                value="cancel"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                class="min-h-11 w-full"
                                data-test="cancel-partner-announcement"
                                :disabled="processing"
                                :aria-busy="processing ? 'true' : undefined"
                            >
                                <Spinner v-if="processing" />
                                <Ban v-else aria-hidden="true" />
                                {{
                                    processing
                                        ? t(
                                              'administration.partner_announcements.cancelling',
                                          )
                                        : t(
                                              'administration.partner_announcements.cancel',
                                          )
                                }}
                            </Button>
                            <InputError :message="errors.decision" />
                        </Form>
                    </div>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
