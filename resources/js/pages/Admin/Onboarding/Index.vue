<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index, update } from '@/routes/admin/onboarding';

type Avatar = {
    id: number;
    name: string;
    image_url: string;
};

type Member = {
    id: number;
    display_name: string;
    email: string;
    status: 'not_started' | 'in_progress' | 'completed' | 'skipped';
    step: string | null;
    updated_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    avatars: Avatar[];
    setting: {
        pass_avatar_id: number | null;
        like_avatar_id: number | null;
    };
    stats: {
        not_started: number;
        in_progress: number;
        completed: number;
        skipped: number;
        completion_rate: number;
    };
    members: {
        data: Member[];
        total: number;
        links: PaginationLink[];
    };
}>();

const statCards = [
    { key: 'not_started' as const, label: 'Pas commencé' },
    { key: 'in_progress' as const, label: 'En cours' },
    { key: 'completed' as const, label: 'Terminé' },
    { key: 'skipped' as const, label: 'Passé' },
];

const statusLabels: Record<Member['status'], string> = {
    not_started: 'Pas commencé',
    in_progress: 'En cours',
    completed: 'Terminé',
    skipped: 'Passé',
};

const stepLabels: Record<string, string> = {
    pass_demo: 'Carte à passer',
    like_demo: 'Carte à liker',
    match_demo: 'Match',
    conversation_demo: 'Conversation',
};

function avatarById(id: number | null): Avatar | undefined {
    return props.avatars.find((avatar) => avatar.id === id);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: dashboard() },
            { title: 'Tutoriel', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Tutoriel produit" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">Administration</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Tutoriel produit
            </h1>
            <p class="mt-1 text-muted-foreground">
                Configurez les profils de démonstration et suivez la progression
                des membres.
            </p>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <Card v-for="stat in statCards" :key="stat.key">
                <CardHeader class="pb-2">
                    <CardDescription>{{ stat.label }}</CardDescription>
                    <CardTitle class="text-3xl">{{
                        stats[stat.key]
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Taux de complétion</CardDescription>
                    <CardTitle class="text-3xl"
                        >{{ stats.completion_rate }} %</CardTitle
                    >
                </CardHeader>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>Profils de démonstration</CardTitle>
                <CardDescription>
                    Choisissez deux avatars actifs et distincts. Ils ne pourront
                    plus être archivés ou supprimés tant qu’ils sont utilisés
                    ici.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="update.form()"
                    class="grid gap-5 lg:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="pass_avatar_id"
                            >Première carte à passer</Label
                        >
                        <select
                            id="pass_avatar_id"
                            name="pass_avatar_id"
                            required
                            :value="setting.pass_avatar_id ?? ''"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>Choisir un avatar</option>
                            <option
                                v-for="avatar in avatars"
                                :key="avatar.id"
                                :value="avatar.id"
                            >
                                {{ avatar.name }}
                            </option>
                        </select>
                        <InputError :message="errors.pass_avatar_id" />
                        <img
                            v-if="avatarById(setting.pass_avatar_id)"
                            :src="avatarById(setting.pass_avatar_id)!.image_url"
                            :alt="avatarById(setting.pass_avatar_id)!.name"
                            class="mt-2 size-24 rounded-2xl object-cover"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="like_avatar_id"
                            >Deuxième carte à liker</Label
                        >
                        <select
                            id="like_avatar_id"
                            name="like_avatar_id"
                            required
                            :value="setting.like_avatar_id ?? ''"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>Choisir un avatar</option>
                            <option
                                v-for="avatar in avatars"
                                :key="avatar.id"
                                :value="avatar.id"
                            >
                                {{ avatar.name }}
                            </option>
                        </select>
                        <InputError :message="errors.like_avatar_id" />
                        <img
                            v-if="avatarById(setting.like_avatar_id)"
                            :src="avatarById(setting.like_avatar_id)!.image_url"
                            :alt="avatarById(setting.like_avatar_id)!.name"
                            class="mt-2 size-24 rounded-2xl object-cover"
                        />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing || avatars.length < 2"
                        class="lg:col-span-2 lg:justify-self-start"
                    >
                        Enregistrer la configuration
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Progression des membres</CardTitle>
                <CardDescription>
                    {{ members.total }} membre{{
                        members.total > 1 ? 's' : ''
                    }}
                    éligible{{ members.total > 1 ? 's' : '' }} au tutoriel.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-3xl text-left text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Membre</th>
                            <th class="px-3 py-2 font-medium">Statut</th>
                            <th class="px-3 py-2 font-medium">Étape</th>
                            <th class="px-3 py-2 font-medium">Mise à jour</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members.data"
                            :key="member.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-3">
                                <span class="block font-medium">{{
                                    member.display_name
                                }}</span>
                                <span class="text-muted-foreground">{{
                                    member.email
                                }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <Badge
                                    :variant="
                                        member.status === 'completed'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ statusLabels[member.status] }}
                                </Badge>
                            </td>
                            <td class="px-3 py-3">
                                {{
                                    member.step
                                        ? (stepLabels[member.step] ??
                                          member.step)
                                        : '—'
                                }}
                            </td>
                            <td class="px-3 py-3 text-muted-foreground">
                                {{ formatDate(member.updated_at) }}
                            </td>
                        </tr>
                        <tr v-if="members.data.length === 0">
                            <td
                                colspan="4"
                                class="px-3 py-8 text-center text-muted-foreground"
                            >
                                Aucun membre éligible pour le moment.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <nav
                    v-if="members.links.length > 3"
                    aria-label="Pagination des membres"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Button
                        v-for="link in members.links"
                        :key="link.label"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="link.url === null"
                    >
                        <Link v-if="link.url" :href="link.url" preserve-scroll>
                            {{
                                link.label
                                    .replace('&laquo;', '‹')
                                    .replace('&raquo;', '›')
                            }}
                        </Link>
                        <span v-else>
                            {{
                                link.label
                                    .replace('&laquo;', '‹')
                                    .replace('&raquo;', '›')
                            }}
                        </span>
                    </Button>
                </nav>
            </CardContent>
        </Card>
    </main>
</template>
