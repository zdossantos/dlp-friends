<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ImagePlus, Send, Save } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
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
import { submit, update } from '@/routes/partner/profile';
import type { PartnerRevision } from '@/types';

const props = defineProps<{
    initialRevision: PartnerRevision | null;
    hasDraft: boolean;
}>();

const { t } = useTranslations();
const previewUrl = ref<string | null>(props.initialRevision?.imageUrl ?? null);
let localPreviewUrl: string | null = null;

function selectImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    const image = input.files?.[0];

    if (localPreviewUrl !== null) {
        URL.revokeObjectURL(localPreviewUrl);
        localPreviewUrl = null;
    }

    if (image === undefined) {
        previewUrl.value = props.initialRevision?.imageUrl ?? null;

        return;
    }

    localPreviewUrl = URL.createObjectURL(image);
    previewUrl.value = localPreviewUrl;
}

onBeforeUnmount(() => {
    if (localPreviewUrl !== null) {
        URL.revokeObjectURL(localPreviewUrl);
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('partners.profile.form.title') }}</CardTitle>
            <CardDescription>
                {{ t('partners.profile.form.description') }}
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <Form
                v-bind="update.form()"
                enctype="multipart/form-data"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-6 lg:grid-cols-2">
                    <fieldset class="space-y-4 rounded-xl border p-4">
                        <legend class="px-2 font-semibold">
                            {{ t('partners.profile.form.french_section') }}
                        </legend>

                        <div class="grid gap-2">
                            <Label for="name_fr">
                                {{ t('partners.profile.form.name_fr') }}
                            </Label>
                            <Input
                                id="name_fr"
                                name="name_fr"
                                :default-value="initialRevision?.nameFr ?? ''"
                                maxlength="100"
                                required
                                class="h-11"
                                :aria-invalid="
                                    errors.name_fr ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.name_fr" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description_fr">
                                {{ t('partners.profile.form.description_fr') }}
                            </Label>
                            <Textarea
                                id="description_fr"
                                name="description_fr"
                                :default-value="
                                    initialRevision?.descriptionFr ?? ''
                                "
                                maxlength="500"
                                required
                                class="min-h-32"
                                :aria-invalid="
                                    errors.description_fr ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.description_fr" />
                        </div>
                    </fieldset>

                    <fieldset class="space-y-4 rounded-xl border p-4">
                        <legend class="px-2 font-semibold">
                            {{ t('partners.profile.form.english_section') }}
                        </legend>

                        <div class="grid gap-2">
                            <Label for="name_en">
                                {{ t('partners.profile.form.name_en') }}
                            </Label>
                            <Input
                                id="name_en"
                                name="name_en"
                                :default-value="initialRevision?.nameEn ?? ''"
                                maxlength="100"
                                required
                                class="h-11"
                                :aria-invalid="
                                    errors.name_en ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.name_en" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description_en">
                                {{ t('partners.profile.form.description_en') }}
                            </Label>
                            <Textarea
                                id="description_en"
                                name="description_en"
                                :default-value="
                                    initialRevision?.descriptionEn ?? ''
                                "
                                maxlength="500"
                                required
                                class="min-h-32"
                                :aria-invalid="
                                    errors.description_en ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.description_en" />
                        </div>
                    </fieldset>
                </div>

                <div
                    class="grid gap-4 rounded-xl border p-4 md:grid-cols-[minmax(0,1fr)_16rem]"
                >
                    <div class="space-y-3">
                        <div class="grid gap-2">
                            <Label for="image">
                                {{ t('partners.profile.form.image') }}
                            </Label>
                            <Input
                                id="image"
                                name="image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="h-11 py-2"
                                :aria-invalid="
                                    errors.image ? 'true' : undefined
                                "
                                @change="selectImage"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('partners.profile.form.image_hint') }}
                            </p>
                            <InputError :message="errors.image" />
                        </div>

                        <div
                            class="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
                        >
                            <ImagePlus
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <p>{{ t('partners.profile.form.image_rights') }}</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border bg-muted/40">
                        <img
                            v-if="previewUrl"
                            :src="previewUrl"
                            :alt="t('partners.profile.form.image_preview_alt')"
                            class="aspect-video size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex aspect-video items-center justify-center text-muted-foreground"
                        >
                            <ImagePlus class="size-10" aria-hidden="true" />
                            <span class="sr-only">
                                {{
                                    t('partners.profile.form.image_preview_alt')
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="processing"
                    :aria-busy="processing ? 'true' : undefined"
                >
                    <Spinner v-if="processing" />
                    <Save v-else aria-hidden="true" />
                    {{ t('partners.profile.form.save') }}
                </Button>
            </Form>

            <div class="border-t pt-6">
                <Form
                    v-bind="submit.form()"
                    :options="{ preserveScroll: true }"
                    class="flex flex-wrap items-center gap-3"
                    v-slot="{ errors, processing }"
                >
                    <Button
                        type="submit"
                        variant="secondary"
                        class="min-h-11"
                        :disabled="processing || !hasDraft"
                        :aria-busy="processing ? 'true' : undefined"
                    >
                        <Spinner v-if="processing" />
                        <Send v-else aria-hidden="true" />
                        {{ t('partners.profile.form.submit') }}
                    </Button>
                    <p v-if="!hasDraft" class="text-sm text-muted-foreground">
                        {{ t('partners.profile.form.submit_hint') }}
                    </p>
                    <div class="basis-full space-y-1">
                        <InputError :message="errors.profile" />
                        <InputError :message="errors.image" />
                    </div>
                </Form>
            </div>
        </CardContent>
    </Card>
</template>
