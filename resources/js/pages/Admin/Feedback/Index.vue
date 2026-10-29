<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useTranslations } from '@/composables/useTranslations';
import { index, show } from '@/routes/admin/feedback';
import type { FeedbackOptions, FeedbackSummary } from '@/types/feedback';
const props = defineProps<{
    feedback: {
        data: FeedbackSummary[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { kind?: string; area?: string; status?: string };
    options: FeedbackOptions;
}>();
const { t, formatDate } = useTranslations();
const form = useForm({
    kind: props.filters.kind ?? '',
    area: props.filters.area ?? '',
    status: props.filters.status ?? '',
});
const fields = ['kind', 'area', 'status'] as const;
const choices = {
    kind: props.options.kinds,
    area: props.options.areas,
    status: props.options.statuses ?? [],
};
const catalogues = {
    kind: 'kinds',
    area: 'areas',
    status: 'statuses',
} as const;
</script>

<template>
    <Head :title="t('feedback.title')" />
    <main
        class="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-6 p-4 sm:p-6"
    >
        <AdminPageHeader
            :title="t('feedback.title')"
            :description="t('feedback.description')"
        />
        <form
            class="grid gap-3 sm:grid-cols-4 sm:items-end"
            @submit.prevent="
                form.get(index.url(), {
                    preserveState: true,
                    preserveScroll: true,
                })
            "
        >
            <div
                v-for="field in fields"
                :key="field"
                class="grid gap-2 *:w-full"
            >
                <Label :for="`feedback-filter-${field}`">{{
                    t(`feedback.${field}`)
                }}</Label>
                <NativeSelect
                    :id="`feedback-filter-${field}`"
                    v-model="form[field]"
                    class="min-h-11"
                    :aria-invalid="!!form.errors[field]"
                    :aria-describedby="`feedback-filter-${field}-error`"
                >
                    <NativeSelectOption value="">{{
                        t('feedback.all')
                    }}</NativeSelectOption>
                    <NativeSelectOption
                        v-for="value in choices[field]"
                        :key="value"
                        :value="value"
                        >{{
                            t(`feedback.${catalogues[field]}.${value}`)
                        }}</NativeSelectOption
                    >
                </NativeSelect>
                <InputError
                    :id="`feedback-filter-${field}-error`"
                    :message="form.errors[field]"
                />
            </div>
            <Button
                class="min-h-11"
                type="submit"
                data-test="feedback-filter-submit"
                :disabled="form.processing"
                >{{ t('feedback.filter') }}</Button
            >
        </form>
        <p v-if="!feedback.data.length" class="text-muted-foreground">
            {{ t('feedback.empty') }}
        </p>
        <ul v-else class="grid gap-3">
            <li v-for="item in feedback.data" :key="item.id">
                <Link
                    :href="show(item.id)"
                    :data-test="`feedback-item-${item.id}`"
                    class="grid gap-2 rounded-xl border p-4 hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <span class="font-medium"
                        >{{ t(`feedback.kinds.${item.kind}`) }} ·
                        {{ t(`feedback.areas.${item.area}`) }}</span
                    >
                    <span class="text-sm">{{
                        t(`feedback.statuses.${item.status}`)
                    }}</span>
                    <span class="text-sm break-words text-muted-foreground"
                        >{{ item.author.name }} ·
                        {{ formatDate(item.created_at) }}</span
                    >
                </Link>
            </li>
        </ul>
        <nav :aria-label="t('feedback.pagination')" class="flex gap-3">
            <Button v-if="feedback.prev_page_url" as-child variant="outline"
                ><Link :href="feedback.prev_page_url">{{
                    t('feedback.previous')
                }}</Link></Button
            >
            <Button v-if="feedback.next_page_url" as-child variant="outline"
                ><Link :href="feedback.next_page_url">{{
                    t('feedback.next')
                }}</Link></Button
            >
        </nav>
    </main>
</template>
