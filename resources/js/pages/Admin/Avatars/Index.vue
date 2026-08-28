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
import { dashboard } from '@/routes';
import {
    destroy,
    index,
    move,
    status,
    store,
    update,
} from '@/routes/admin/avatars';
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

function selectedFileName(event: Event): string {
    return (event.target as HTMLInputElement).files?.[0]?.name ?? '';
}

function selectNewImage(event: Event): void {
    newImageName.value = selectedFileName(event);
}

function selectReplacementImage(avatarId: number, event: Event): void {
    replacementImageNames.value[avatarId] = selectedFileName(event);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: dashboard() },
            { title: 'Avatars', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Avatars" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">Administration</p>
            <h1 class="text-3xl font-semibold tracking-tight">Avatars</h1>
            <p class="mt-1 text-muted-foreground">
                Gérez les images proposées aux membres et leurs fonds colorés.
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Ajouter un avatar</CardTitle>
                <CardDescription>
                    Formats PNG ou WebP, 2 Mo et 1 200 pixels maximum.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="store.form()"
                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-start"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="new_avatar_name">Nom</Label>
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
                        <Label for="new_avatar_primary_color">Couleur 1</Label>
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
                        <Label for="new_avatar_secondary_color"
                            >Couleur 2</Label
                        >
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
                        <Label for="new_avatar_image">Image</Label>
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
                            Choisir une image
                        </Label>
                        <span class="text-sm text-muted-foreground">
                            {{ newImageName || 'Aucun fichier sélectionné' }}
                        </span>
                        <InputError :message="errors.image" />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="lg:col-span-4 lg:justify-self-start"
                    >
                        Ajouter
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <section
            aria-labelledby="avatar-catalog-title"
            class="grid gap-4 xl:grid-cols-2"
        >
            <h2 id="avatar-catalog-title" class="sr-only">Catalogue</h2>

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
                            {{ avatar.is_active ? 'Actif' : 'Archivé' }}
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
                                <Label :for="`avatar-name-${avatar.id}`"
                                    >Nom</Label
                                >
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
                                    <Label :for="`avatar-primary-${avatar.id}`"
                                        >Couleur 1</Label
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
                                        >Couleur 2</Label
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
                                    Remplacer l’image
                                    <span class="text-muted-foreground"
                                        >(facultatif)</span
                                    >
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
                                    Choisir une image
                                </Label>
                                <span class="text-sm text-muted-foreground">
                                    {{
                                        replacementImageNames[avatar.id] ||
                                        'Aucun fichier sélectionné'
                                    }}
                                </span>
                                <InputError :message="errors.image" />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                Enregistrer
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
                                    :aria-label="`Monter ${avatar.name}`"
                                >
                                    Monter
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
                                    :aria-label="`Descendre ${avatar.name}`"
                                >
                                    Descendre
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
                                    :aria-label="`${avatar.is_active ? 'Archiver' : 'Réactiver'} ${avatar.name}`"
                                >
                                    {{
                                        avatar.is_active
                                            ? 'Archiver'
                                            : 'Réactiver'
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
                                        :aria-label="`Supprimer ${avatar.name}`"
                                    >
                                        Supprimer
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        v-bind="destroy.form(avatar)"
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ errors, processing }"
                                    >
                                        <DialogHeader>
                                            <DialogTitle
                                                >Supprimer l’avatar
                                                {{ avatar.name }}</DialogTitle
                                            >
                                            <DialogDescription>
                                                Cette action supprime
                                                définitivement son image.
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
                                                    >Annuler</Button
                                                >
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                :disabled="processing"
                                            >
                                                Supprimer
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ avatar.profiles_count }}
                            {{
                                avatar.profiles_count === 1
                                    ? 'profil utilise'
                                    : 'profils utilisent'
                            }}
                            cet avatar.
                        </p>
                        <p
                            v-if="avatar.used_by_onboarding"
                            class="text-xs font-medium text-primary"
                        >
                            Utilisé par le tutoriel : remplacez-le dans sa
                            configuration avant de l’archiver ou de le
                            supprimer.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="avatars.length === 0"
                class="rounded-xl border border-dashed p-6 text-muted-foreground"
            >
                Aucun avatar dans le catalogue.
            </p>
        </section>
    </main>
</template>
