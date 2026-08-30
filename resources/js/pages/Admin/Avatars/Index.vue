<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, move, status, store, update } from '@/routes/admin/avatars';
import type { AvatarOption } from '@/types';

type AdminAvatar = AvatarOption & {
    is_active: boolean;
    sort_order: number;
    profiles_count: number;
    used_by_onboarding: boolean;
};

defineProps<{ avatars: AdminAvatar[] }>();

const newImageName = ref('');
const replacementImageNames = ref<Record<number, string>>({});
const { t } = useTranslations();

function selectedFileName(event: Event): string {
    return (event.target as HTMLInputElement).files?.[0]?.name ?? '';
}

function selectNewImage(event: Event): void {
    newImageName.value = selectedFileName(event);
}

function selectReplacementImage(avatarId: number, event: Event): void {
    replacementImageNames.value[avatarId] = selectedFileName(event);
}
</script>

<template>
    <Head :title="t('administration.avatars.title')" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ t('administration.avatars.title') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ t('administration.avatars.description') }}
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('administration.avatars.add_title')
                }}</CardTitle>
                <CardDescription>
                    {{ t('administration.avatars.add_description') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="store.form()"
                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-start"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="new_avatar_name">{{
                            t('administration.common.name')
                        }}</Label>
                        <Input
                            id="new_avatar_name"
                            name="name"
                            required
                            maxlength="80"
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new_avatar_primary_color">{{
                            t('administration.avatars.primary_color')
                        }}</Label>
                        <Input
                            id="new_avatar_primary_color"
                            name="primary_color"
                            type="color"
                            default-value="#7C3AED"
                            required
                            class="w-full lg:w-24"
                        />
                        <InputError :message="errors.primary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new_avatar_secondary_color">{{
                            t('administration.avatars.secondary_color')
                        }}</Label>
                        <Input
                            id="new_avatar_secondary_color"
                            name="secondary_color"
                            type="color"
                            default-value="#EC4899"
                            required
                            class="w-full lg:w-24"
                        />
                        <InputError :message="errors.secondary_color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new_avatar_image">{{
                            t('administration.avatars.image')
                        }}</Label>
                        <input
                            id="new_avatar_image"
                            name="image"
                            type="file"
                            accept="image/png,image/webp"
                            required
                            data-test="avatar-file-input"
                            class="sr-only"
                            @change="selectNewImage"
                        />
                        <Label
                            for="new_avatar_image"
                            class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-md border bg-background px-4 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                        >
                            {{ t('administration.avatars.choose_image') }}
                        </Label>
                        <span class="text-sm text-muted-foreground">
                            {{
                                newImageName ||
                                t('administration.avatars.no_file')
                            }}
                        </span>
                        <InputError :message="errors.image" />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="lg:col-span-4 lg:justify-self-start"
                    >
                        {{ t('administration.common.add') }}
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <section
            aria-labelledby="avatar-catalog-title"
            class="grid gap-4 xl:grid-cols-2"
        >
            <h2 id="avatar-catalog-title" class="sr-only">
                {{ t('administration.common.catalogue') }}
            </h2>

            <Card v-for="(avatar, indexInCatalog) in avatars" :key="avatar.id">
                <CardContent class="grid gap-4 p-4 sm:grid-cols-[8rem_1fr]">
                    <div>
                        <AvatarPortrait
                            :avatar="avatar"
                            :data-test="`avatar-preview-${avatar.id}`"
                            class="shadow-md"
                        />
                        <Badge
                            class="mt-2 w-full justify-center"
                            :variant="
                                avatar.is_active ? 'default' : 'secondary'
                            "
                        >
                            {{
                                avatar.is_active
                                    ? t('administration.common.active')
                                    : t('administration.common.archived')
                            }}
                        </Badge>
                    </div>

                    <div class="space-y-4">
                        <Form
                            v-bind="update.form(avatar)"
                            :options="{ preserveScroll: true }"
                            class="grid gap-3"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label :for="`avatar-name-${avatar.id}`">{{
                                    t('administration.common.name')
                                }}</Label>
                                <Input
                                    :id="`avatar-name-${avatar.id}`"
                                    name="name"
                                    :default-value="avatar.name"
                                    required
                                    maxlength="80"
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label
                                        :for="`avatar-primary-${avatar.id}`"
                                        >{{
                                            t(
                                                'administration.avatars.primary_color',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        :id="`avatar-primary-${avatar.id}`"
                                        name="primary_color"
                                        type="color"
                                        :default-value="avatar.primary_color"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        :for="`avatar-secondary-${avatar.id}`"
                                        >{{
                                            t(
                                                'administration.avatars.secondary_color',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        :id="`avatar-secondary-${avatar.id}`"
                                        name="secondary_color"
                                        type="color"
                                        :default-value="avatar.secondary_color"
                                        required
                                    />
                                </div>
                            </div>
                            <InputError
                                :message="
                                    errors.primary_color ??
                                    errors.secondary_color
                                "
                            />
                            <div class="grid gap-2">
                                <Label :for="`avatar-image-${avatar.id}`">
                                    {{
                                        t(
                                            'administration.avatars.replace_image',
                                        )
                                    }}
                                    <span class="text-muted-foreground">{{
                                        t('administration.avatars.optional')
                                    }}</span>
                                </Label>
                                <input
                                    :id="`avatar-image-${avatar.id}`"
                                    name="image"
                                    type="file"
                                    accept="image/png,image/webp"
                                    class="sr-only"
                                    @change="
                                        selectReplacementImage(
                                            avatar.id,
                                            $event,
                                        )
                                    "
                                />
                                <Label
                                    :for="`avatar-image-${avatar.id}`"
                                    class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-md border bg-background px-4 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                                >
                                    {{
                                        t('administration.avatars.choose_image')
                                    }}
                                </Label>
                                <span class="text-sm text-muted-foreground">
                                    {{
                                        replacementImageNames[avatar.id] ||
                                        t('administration.avatars.no_file')
                                    }}
                                </span>
                                <InputError :message="errors.image" />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                {{ t('administration.common.save') }}
                            </Button>
                        </Form>

                        <div class="flex flex-wrap gap-2">
                            <Form
                                v-bind="move.form(avatar)"
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="direction"
                                    value="up"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="
                                        processing || indexInCatalog === 0
                                    "
                                    :aria-label="
                                        t(
                                            'administration.common.move_up_named',
                                            { name: avatar.name },
                                        )
                                    "
                                >
                                    {{ t('administration.common.move_up') }}
                                </Button>
                            </Form>
                            <Form
                                v-bind="move.form(avatar)"
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="direction"
                                    value="down"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="
                                        processing ||
                                        indexInCatalog === avatars.length - 1
                                    "
                                    :aria-label="
                                        t(
                                            'administration.common.move_down_named',
                                            { name: avatar.name },
                                        )
                                    "
                                >
                                    {{ t('administration.common.move_down') }}
                                </Button>
                            </Form>
                            <Form
                                v-bind="status.form(avatar)"
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="is_active"
                                    :value="avatar.is_active ? '0' : '1'"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="
                                        processing ||
                                        (avatar.is_active &&
                                            avatar.used_by_onboarding)
                                    "
                                    :aria-label="
                                        t(
                                            avatar.is_active
                                                ? 'administration.common.archive_named'
                                                : 'administration.common.reactivate_named',
                                            { name: avatar.name },
                                        )
                                    "
                                >
                                    {{
                                        avatar.is_active
                                            ? t('administration.common.archive')
                                            : t(
                                                  'administration.common.reactivate',
                                              )
                                    }}
                                </Button>
                            </Form>

                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        :disabled="
                                            avatar.profiles_count > 0 ||
                                            avatar.used_by_onboarding
                                        "
                                        :aria-label="
                                            t(
                                                'administration.common.delete_named',
                                                { name: avatar.name },
                                            )
                                        "
                                    >
                                        {{ t('administration.common.delete') }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        v-bind="destroy.form(avatar)"
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ errors, processing }"
                                    >
                                        <DialogHeader>
                                            <DialogTitle>{{
                                                t(
                                                    'administration.avatars.delete_title',
                                                    { name: avatar.name },
                                                )
                                            }}</DialogTitle>
                                            <DialogDescription>
                                                {{
                                                    t(
                                                        'administration.avatars.delete_description',
                                                    )
                                                }}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <InputError
                                            class="my-4"
                                            :message="errors.avatar"
                                        />
                                        <DialogFooter class="mt-6 gap-2">
                                            <DialogClose as-child>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    >{{
                                                        t(
                                                            'common.actions.cancel',
                                                        )
                                                    }}</Button
                                                >
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                :disabled="processing"
                                            >
                                                {{
                                                    t(
                                                        'administration.common.delete',
                                                    )
                                                }}
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    avatar.profiles_count === 1
                                        ? 'administration.avatars.profile_uses'
                                        : 'administration.avatars.profiles_use',
                                    { count: avatar.profiles_count },
                                )
                            }}
                        </p>
                        <p
                            v-if="avatar.used_by_onboarding"
                            class="text-xs font-medium text-primary"
                        >
                            {{ t('administration.avatars.used_onboarding') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="avatars.length === 0"
                class="rounded-xl border border-dashed p-6 text-muted-foreground"
            >
                {{ t('administration.avatars.empty') }}
            </p>
        </section>
    </main>
</template>
