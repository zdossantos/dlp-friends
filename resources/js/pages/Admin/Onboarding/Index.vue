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
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/admin/onboarding';

type Avatar = {
    id: number;
    name: string;
    image_url: string;
};

type Member = {
    id: number;
    display_name: string;
    email: string;
    status: 'not_started' | 'in_progress' | 'completed';
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
        completion_rate: number;
    };
    members: {
        data: Member[];
        total: number;
        links: PaginationLink[];
    };
}>();
const { formatDate: formatLocalizedDate, t } = useTranslations();

const statCards = [
    {
        key: 'not_started' as const,
        label: t('administration.onboarding.not_started'),
    },
    {
        key: 'in_progress' as const,
        label: t('administration.onboarding.in_progress'),
    },
    {
        key: 'completed' as const,
        label: t('administration.onboarding.completed'),
    },
];

const statusLabels: Record<Member['status'], string> = {
    not_started: t('administration.onboarding.not_started'),
    in_progress: t('administration.onboarding.in_progress'),
    completed: t('administration.onboarding.completed'),
};

const stepLabels: Record<string, string> = {
    pass_demo: t('administration.onboarding.pass_demo'),
    like_demo: t('administration.onboarding.discover_demo'),
    match_demo: t('administration.onboarding.crossed_worlds_demo'),
    conversation_demo: t('administration.onboarding.conversation_demo'),
};

function avatarById(id: number | null): Avatar | undefined {
    return props.avatars.find((avatar) => avatar.id === id);
}

function formatDate(value: string): string {
    return formatLocalizedDate(value, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="t('administration.onboarding.page_title')" />

    <main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ t('administration.onboarding.page_title') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ t('administration.onboarding.description') }}
            </p>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                    <CardDescription>{{
                        t('administration.onboarding.completion_rate')
                    }}</CardDescription>
                    <CardTitle class="text-3xl"
                        >{{ stats.completion_rate }} %</CardTitle
                    >
                </CardHeader>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('administration.onboarding.profiles_title')
                }}</CardTitle>
                <CardDescription>
                    {{ t('administration.onboarding.profiles_description') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="update.form()"
                    class="grid gap-5 lg:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="pass_avatar_id">{{
                            t('administration.onboarding.pass_avatar')
                        }}</Label>
                        <select
                            id="pass_avatar_id"
                            name="pass_avatar_id"
                            required
                            :value="setting.pass_avatar_id ?? ''"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>
                                {{
                                    t('administration.onboarding.choose_avatar')
                                }}
                            </option>
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
                        <Label for="like_avatar_id">{{
                            t('administration.onboarding.discover_avatar')
                        }}</Label>
                        <select
                            id="like_avatar_id"
                            name="like_avatar_id"
                            required
                            :value="setting.like_avatar_id ?? ''"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" disabled>
                                {{
                                    t('administration.onboarding.choose_avatar')
                                }}
                            </option>
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
                        {{ t('administration.onboarding.save') }}
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('administration.onboarding.members_title')
                }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            members.total === 1
                                ? 'administration.onboarding.eligible_member'
                                : 'administration.onboarding.eligible_members',
                            { count: members.total },
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-3xl text-left text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ t('administration.onboarding.member') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ t('administration.onboarding.status') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ t('administration.onboarding.step') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ t('administration.onboarding.updated_at') }}
                            </th>
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
                                {{ t('administration.onboarding.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <nav
                    v-if="members.links.length > 3"
                    :aria-label="t('administration.onboarding.pagination')"
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
