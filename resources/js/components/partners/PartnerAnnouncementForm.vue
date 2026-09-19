<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
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
import { store, update } from '@/routes/partner/announcements';
import type { PartnerAnnouncement } from '@/types';

defineProps<{ announcement: PartnerAnnouncement | null }>();

const { t } = useTranslations();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>
                {{
                    announcement
                        ? t('partners.announcements.form.edit_title')
                        : t('partners.announcements.form.create_title')
                }}
            </CardTitle>
            <CardDescription>
                {{ t('partners.announcements.form.description') }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <Form
                v-bind="
                    announcement ? update.form(announcement.id) : store.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-5"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="title">
                        {{ t('partners.announcements.form.title') }}
                    </Label>
                    <Input
                        id="title"
                        name="title"
                        :default-value="announcement?.title ?? ''"
                        maxlength="80"
                        required
                        class="h-11"
                        :aria-invalid="errors.title ? 'true' : undefined"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('partners.announcements.form.title_hint') }}
                    </p>
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="content">
                        {{ t('partners.announcements.form.content') }}
                    </Label>
                    <Textarea
                        id="content"
                        name="content"
                        :default-value="announcement?.content ?? ''"
                        maxlength="500"
                        required
                        class="min-h-36"
                        :aria-invalid="errors.content ? 'true' : undefined"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('partners.announcements.form.content_hint') }}
                    </p>
                    <InputError :message="errors.content" />
                </div>

                <div class="grid gap-2">
                    <Label for="destination_url">
                        {{ t('partners.announcements.form.destination_url') }}
                    </Label>
                    <Input
                        id="destination_url"
                        name="destination_url"
                        type="url"
                        inputmode="url"
                        :default-value="announcement?.destinationUrl ?? ''"
                        maxlength="2048"
                        required
                        class="h-11"
                        :placeholder="
                            t(
                                'partners.announcements.form.destination_placeholder',
                            )
                        "
                        :aria-invalid="
                            errors.destination_url ? 'true' : undefined
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('partners.announcements.form.destination_hint') }}
                    </p>
                    <InputError :message="errors.destination_url" />
                </div>

                <InputError :message="errors.announcement" />
                <InputError :message="errors.profile" />

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
                            ? t('partners.announcements.form.saving')
                            : t('partners.announcements.form.save')
                    }}
                </Button>
            </Form>
        </CardContent>
    </Card>
</template>
