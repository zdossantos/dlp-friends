<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import SetMemberBanDialog from '@/components/admin/SetMemberBanDialog.vue';
import BackLink from '@/components/common/BackLink.vue';
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { index, close } from '@/routes/admin/trip-departure-reports';
import type { TripDepartureReport } from '@/types/moderation';
const props = defineProps<{ report: TripDepartureReport }>();
const { t, formatDate } = useTranslations();
const confirming = ref(false);
const form = useForm({ decision: '', confirmed: true });
function submit() {
    form.patch(close(props.report.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
        },
    });
}
</script>
<template>
    <Head :title="t('moderation.trip_reports')" />
    <main class="grid min-w-0 gap-6 p-4 sm:p-6">
        <AdminPageHeader
            :title="t('moderation.trip_reports')"
            :description="t('moderation.trip_report_description')"
        />
        <div class="flex flex-wrap items-center gap-3">
            <Button as-child variant="outline"
                ><BackLink :href="index().url">{{
                    t('moderation.back_to_list')
                }}</BackLink></Button
            ><Badge :variant="report.closed_at ? 'secondary' : 'default'">{{
                t(
                    report.closed_at
                        ? 'moderation.state_closed'
                        : 'moderation.state_open',
                )
            }}</Badge>
        </div>
        <section
            class="grid min-w-0 gap-4 rounded-xl border bg-card p-4 sm:p-6"
        >
            <p class="text-muted-foreground">
                {{ t('moderation.trip_report_scope') }}
            </p>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="role in ['reporter', 'target'] as const"
                    :key="role"
                    class="min-w-0"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ t(`moderation.${role}`) }}
                    </dt>
                    <dd class="break-words">{{ report[role].name }}</dd>
                </div>
                <div
                    v-for="field in [
                        'scheduled_departure_at',
                        'left_at',
                    ] as const"
                    :key="field"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{
                            t(
                                field === 'left_at'
                                    ? 'moderation.trip_left'
                                    : 'moderation.trip_departure',
                            )
                        }}
                    </dt>
                    <dd>
                        {{
                            formatDate(report[field], {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })
                        }}
                    </dd>
                </div>
            </dl>
            <p class="break-words whitespace-pre-wrap">{{ report.details }}</p>
        </section>
        <section class="grid gap-4 rounded-xl border bg-card p-4 sm:p-6">
            <h2 class="text-lg font-semibold">
                {{ t('moderation.processing') }}
            </h2>
            <p v-if="report.closed_at" class="break-words whitespace-pre-wrap">
                {{ report.decision }}
            </p>
            <form v-else class="grid gap-3" @submit.prevent="confirming = true">
                <Label for="trip-decision">{{
                    t('moderation.decision_reason')
                }}</Label>
                <Textarea
                    id="trip-decision"
                    v-model="form.decision"
                    required
                    maxlength="1000"
                />
                <InputError :message="form.errors.decision" /><InputError
                    :message="form.errors.confirmed"
                />
                <Button
                    type="submit"
                    class="justify-self-start"
                    :disabled="form.processing"
                    data-test="close-trip-report"
                    >{{ t('moderation.close_confirm') }}</Button
                >
            </form>
            <SetMemberBanDialog
                v-if="report.target.id && report.target.can_ban"
                :member-id="report.target.id"
                :banned="report.target.banned"
            />
        </section>
        <ResponsiveConfirmation
            v-model:open="confirming"
            :title="t('moderation.close_confirm')"
            :description="t('moderation.confirm_description')"
            :confirm-label="t('moderation.close_confirm')"
            :busy="form.processing"
            test-prefix="trip-close"
            @confirm="submit"
        />
    </main>
</template>
