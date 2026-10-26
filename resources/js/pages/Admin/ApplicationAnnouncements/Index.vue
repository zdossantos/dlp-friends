<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/composables/useTranslations';
import { index, show, store } from '@/routes/admin/application-announcements';
import type { PaginationLink } from '@/types/notification';

const props = defineProps<{
    search: string;
    recipients: { id: number; label: string; email: string }[];
    announcements: {
        data: {
            id: number;
            title_fr: string;
            title_en: string;
            sent_at: string | null;
            deliveries_count: number;
        }[];
        links: PaginationLink[];
    };
}>();
const { t, locale } = useTranslations();
const searchTerm = ref(props.search);
const selectedLabels = ref<Record<number, string>>({});
const form = useForm('application-announcement-draft', {
    title_fr: '',
    title_en: '',
    content_fr: '',
    content_en: '',
    destination_url: '',
    is_release: false,
    send_push: false,
    audience: 'all',
    recipient_ids: [] as number[],
});
function selectRecipient(id: number, label: string, checked: boolean): void {
    form.recipient_ids = checked
        ? [...form.recipient_ids, id]
        : form.recipient_ids.filter((value) => value !== id);
    selectedLabels.value[id] = label;
}
function searchRecipients(): void {
    router.get(
        index().url,
        { search: searchTerm.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['recipients', 'search'],
        },
    );
}
</script>

<template>
    <Head :title="t('application_announcements.title')" />
    <main class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
        <h1 class="text-2xl font-semibold">
            {{ t('application_announcements.title') }}
        </h1>
        <p class="text-sm text-muted-foreground">
            {{ t('application_announcements.description') }}
        </p>
        <form
            class="space-y-6 rounded-2xl border bg-card p-4 sm:p-6"
            @submit.prevent="form.post(store().url)"
        >
            <fieldset
                v-for="language in ['fr', 'en'] as const"
                :key="language"
                class="space-y-3"
            >
                <legend class="mb-3 font-semibold">
                    {{ t(`application_announcements.languages.${language}`) }}
                </legend>
                <Label :for="`title_${language}`">{{
                    t('application_announcements.fields.title')
                }}</Label>
                <Input
                    :id="`title_${language}`"
                    v-model="form[`title_${language}`]"
                    maxlength="80"
                    required
                />
                <InputError :message="form.errors[`title_${language}`]" />
                <Label :for="`content_${language}`">{{
                    t('application_announcements.fields.content')
                }}</Label>
                <Textarea
                    :id="`content_${language}`"
                    v-model="form[`content_${language}`]"
                    maxlength="5000"
                    rows="6"
                    required
                />
                <InputError :message="form.errors[`content_${language}`]" />
            </fieldset>
            <div class="space-y-3">
                <label
                    for="is_release"
                    class="flex min-h-11 items-center gap-3 text-sm font-medium"
                >
                    <Checkbox id="is_release" v-model="form.is_release" />{{
                        t('application_announcements.fields.is_release')
                    }}
                </label>
                <p class="text-sm text-muted-foreground">
                    {{ t('application_announcements.release_help') }}
                </p>
                <template v-if="!form.is_release">
                    <Label for="destination_url">{{
                        t('application_announcements.fields.link')
                    }}</Label>
                    <Input
                        id="destination_url"
                        v-model="form.destination_url"
                        type="url"
                        maxlength="2048"
                    />
                    <InputError :message="form.errors.destination_url" />
                </template>
                <label
                    for="send_push"
                    class="flex min-h-11 items-center gap-3 text-sm font-medium"
                >
                    <Checkbox id="send_push" v-model="form.send_push" />{{
                        t('application_announcements.fields.send_push')
                    }}
                </label>
                <p class="text-sm text-muted-foreground">
                    {{ t('application_announcements.push_help') }}
                </p>
            </div>
            <div class="space-y-3">
                <Label for="audience">{{
                    t('application_announcements.fields.audience')
                }}</Label>
                <NativeSelect id="audience" v-model="form.audience">
                    <NativeSelectOption value="all">{{
                        t('application_announcements.audiences.all')
                    }}</NativeSelectOption>
                    <NativeSelectOption value="selected">{{
                        t('application_announcements.audiences.selected')
                    }}</NativeSelectOption>
                </NativeSelect>
                <p class="text-sm text-muted-foreground">
                    {{ t('application_announcements.audience_help') }}
                </p>
                <InputError :message="form.errors.audience" />
                <div v-if="form.audience === 'selected'" class="space-y-3">
                    <Label for="recipient-search">{{
                        t('application_announcements.search')
                    }}</Label>
                    <div class="flex gap-2">
                        <Input
                            id="recipient-search"
                            v-model="searchTerm"
                            maxlength="100"
                            @keydown.enter.prevent="searchRecipients"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="searchRecipients"
                            >{{ t('application_announcements.search') }}</Button
                        >
                    </div>
                    <p class="text-sm">
                        {{
                            t('application_announcements.selected_count', {
                                count: form.recipient_ids.length,
                            })
                        }}
                    </p>
                    <ul
                        v-if="form.recipient_ids.length"
                        class="flex flex-wrap gap-2"
                    >
                        <li v-for="id in form.recipient_ids" :key="id">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                :aria-label="
                                    t(
                                        'application_announcements.remove_recipient',
                                        { recipient: selectedLabels[id] ?? id },
                                    )
                                "
                                @click="
                                    selectRecipient(
                                        id,
                                        selectedLabels[id] ?? String(id),
                                        false,
                                    )
                                "
                                >{{ selectedLabels[id] ?? id }} ×</Button
                            >
                        </li>
                    </ul>
                    <div
                        class="max-h-72 space-y-2 overflow-y-auto rounded-xl border p-3"
                    >
                        <label
                            v-for="recipient in recipients"
                            :key="recipient.id"
                            class="flex min-h-11 items-center gap-3 text-sm break-all"
                        >
                            <Checkbox
                                :model-value="
                                    form.recipient_ids.includes(recipient.id)
                                "
                                @update:model-value="
                                    selectRecipient(
                                        recipient.id,
                                        recipient.email,
                                        $event === true,
                                    )
                                "
                            />
                            <span
                                >{{ recipient.label }}
                                <span class="text-muted-foreground">{{
                                    recipient.email
                                }}</span></span
                            >
                        </label>
                        <p
                            v-if="!recipients.length"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('application_announcements.no_recipients') }}
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('application_announcements.search_help') }}
                    </p>
                    <InputError
                        v-for="(error, key) in form.errors"
                        :key="key"
                        :message="
                            key.startsWith('recipient_ids') ? error : undefined
                        "
                    />
                </div>
            </div>
            <Button :disabled="form.processing" :aria-busy="form.processing">{{
                t('application_announcements.preview')
            }}</Button>
        </form>
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('application_announcements.history') }}
            </h2>
            <ul class="divide-y rounded-xl border bg-card">
                <li
                    v-for="announcement in announcements.data"
                    :key="announcement.id"
                >
                    <Link
                        :href="show(announcement.id)"
                        class="flex min-h-14 flex-wrap items-center justify-between gap-2 p-4 hover:bg-muted"
                    >
                        <span>{{
                            locale === 'en'
                                ? announcement.title_en
                                : announcement.title_fr
                        }}</span>
                        <span class="text-sm text-muted-foreground"
                            >{{
                                t(
                                    announcement.sent_at
                                        ? 'application_announcements.sent'
                                        : 'application_announcements.draft',
                                )
                            }}
                            · {{ announcement.deliveries_count }}</span
                        >
                    </Link>
                </li>
            </ul>
            <nav
                class="flex flex-wrap gap-2"
                :aria-label="t('application_announcements.pagination')"
            >
                <Button
                    v-for="(link, position) in announcements.links"
                    :key="position"
                    type="button"
                    variant="outline"
                    :disabled="!link.url || link.active"
                    :aria-current="link.active ? 'page' : undefined"
                    @click="link.url && router.get(link.url)"
                    >{{
                        position === 0
                            ? t('application_announcements.previous')
                            : position === announcements.links.length - 1
                              ? t('application_announcements.next')
                              : link.label
                    }}</Button
                >
            </nav>
        </section>
    </main>
</template>
