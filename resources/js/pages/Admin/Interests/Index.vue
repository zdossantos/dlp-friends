<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
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
import { update as updateSetting } from '@/routes/admin/interest-setting';
import {
    destroy,
    index,
    move,
    status,
    store,
    update,
} from '@/routes/admin/interests';

type Interest = {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
    profiles_count: number;
};

defineProps<{
    interests: Interest[];
    setting: {
        max_selections: number;
    };
}>();

const profileCountLabel = (count: number): string =>
    `${count} ${count === 1 ? 'profil' : 'profils'}`;

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Administration',
                href: dashboard(),
            },
            {
                title: 'Intérêts',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Intérêts" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">Administration</p>
            <h1 class="text-3xl font-semibold tracking-tight">Intérêts</h1>
            <p class="mt-1 text-muted-foreground">
                Gérez le catalogue visible par les membres et conservez son
                historique d’utilisation.
            </p>
        </header>

        <section
            data-test="catalog-controls"
            class="grid items-start gap-4 lg:grid-cols-2"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Ajouter un intérêt</CardTitle>
                    <CardDescription>
                        Les nouveaux intérêts sont ajoutés à la fin du
                        catalogue.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="store.form()"
                        data-test="create-interest-form"
                        class="grid gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <Label for="new_interest_name">Nom</Label>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="new_interest_name"
                                name="name"
                                class="flex-1"
                                autocomplete="off"
                                placeholder="Ex. Parades"
                            />
                            <Button type="submit" :disabled="processing">
                                Ajouter
                            </Button>
                        </div>
                        <InputError :message="errors.name" />
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Limite de sélection</CardTitle>
                    <CardDescription>
                        Nombre maximal d’intérêts actifs qu’un membre peut
                        sélectionner.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="updateSetting.form()"
                        class="grid gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <Label for="max_selections">Maximum par membre</Label>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="max_selections"
                                name="max_selections"
                                type="number"
                                min="1"
                                max="100"
                                class="flex-1"
                                :default-value="setting.max_selections"
                            />
                            <Button type="submit" :disabled="processing">
                                Enregistrer
                            </Button>
                        </div>
                        <InputError :message="errors.max_selections" />
                    </Form>
                </CardContent>
            </Card>
        </section>

        <section aria-labelledby="interest-catalog-title" class="space-y-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="interest-catalog-title" class="text-lg font-semibold">
                    Catalogue
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ interests.length }}
                    {{ interests.length === 1 ? 'intérêt' : 'intérêts' }}
                </p>
            </div>

            <p
                v-if="interests.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Aucun intérêt n’a encore été créé.
            </p>

            <Card
                v-for="(interest, position) in interests"
                :key="interest.id"
                class="gap-0 py-0"
            >
                <CardContent class="space-y-2 p-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
                        <Form
                            v-bind="update.form(interest)"
                            data-test="edit-interest-form"
                            class="grid min-w-0 flex-1 gap-2"
                            v-slot="{ errors, processing }"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center"
                            >
                                <Input
                                    :id="`interest-name-${interest.id}`"
                                    name="name"
                                    class="min-w-0 flex-1"
                                    :aria-label="`Nom de l’intérêt ${interest.name}`"
                                    :default-value="interest.name"
                                    autocomplete="off"
                                />
                                <Badge
                                    class="self-start sm:self-auto"
                                    :variant="
                                        interest.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        interest.is_active ? 'Actif' : 'Archivé'
                                    }}
                                </Badge>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    Enregistrer
                                </Button>
                            </div>
                            <InputError :message="errors.name" />
                        </Form>

                        <div
                            class="flex shrink-0 flex-wrap gap-2"
                            aria-label="Réordonner l’intérêt"
                        >
                            <Form
                                v-bind="move.form(interest)"
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
                                    size="sm"
                                    :aria-label="`Monter ${interest.name}`"
                                    :disabled="processing || position === 0"
                                >
                                    Monter
                                </Button>
                            </Form>
                            <Form
                                v-bind="move.form(interest)"
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
                                    size="sm"
                                    :aria-label="`Descendre ${interest.name}`"
                                    :disabled="
                                        processing ||
                                        position === interests.length - 1
                                    "
                                >
                                    Descendre
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 border-t pt-2 sm:flex-row sm:items-center"
                    >
                        <div class="text-sm text-muted-foreground">
                            <p>
                                {{ profileCountLabel(interest.profiles_count) }}
                                dans l’historique
                            </p>
                            <p
                                v-if="interest.profiles_count > 0"
                                :id="`delete-interest-help-${interest.id}`"
                                class="text-xs"
                            >
                                Cet intérêt apparaît dans l’historique et ne
                                peut pas être supprimé.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template v-if="interest.is_active">
                                <Dialog>
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :aria-label="`Archiver ${interest.name}`"
                                        >
                                            Archiver
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="status.form(interest)"
                                            class="space-y-6"
                                            v-slot="{ errors, processing }"
                                        >
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value="0"
                                            />
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Archiver l’intérêt
                                                    {{ interest.name }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Il disparaîtra du catalogue
                                                    membre. Son historique sera
                                                    conservé.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <InputError
                                                :message="errors.is_active"
                                            />
                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                    >
                                                        Annuler
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    :disabled="processing"
                                                >
                                                    Archiver
                                                </Button>
                                            </DialogFooter>
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </template>
                            <Form
                                v-else
                                v-bind="status.form(interest)"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="1"
                                />
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    :aria-label="`Réactiver ${interest.name}`"
                                    :disabled="processing"
                                >
                                    Réactiver
                                </Button>
                            </Form>

                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        :aria-label="`Supprimer ${interest.name}`"
                                        :aria-describedby="
                                            interest.profiles_count > 0
                                                ? `delete-interest-help-${interest.id}`
                                                : undefined
                                        "
                                        :disabled="interest.profiles_count > 0"
                                    >
                                        Supprimer
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        v-bind="destroy.form(interest)"
                                        class="space-y-6"
                                        v-slot="{ errors, processing }"
                                    >
                                        <DialogHeader>
                                            <DialogTitle>
                                                Supprimer l’intérêt
                                                {{ interest.name }}
                                            </DialogTitle>
                                            <DialogDescription>
                                                Cette action est définitive.
                                                Seuls les intérêts jamais
                                                utilisés peuvent être supprimés.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <InputError
                                            :message="errors.interest"
                                        />
                                        <DialogFooter class="gap-2">
                                            <DialogClose as-child>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    Annuler
                                                </Button>
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
                    </div>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
