<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Building2, EyeOff } from '@lucide/vue';
import PartnerModerationCard from '@/components/partners/PartnerModerationCard.vue';
import type { ModeratedPartnerRevision } from '@/components/partners/PartnerModerationCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { order, unpublish } from '@/routes/admin/partner-profiles';

type PublishedPartnerProfile = {
    id: number;
    position: number;
    revision: ModeratedPartnerRevision;
};

const props = defineProps<{
    pendingRevisions: ModeratedPartnerRevision[];
    publishedProfiles: PublishedPartnerProfile[];
}>();

const { t } = useTranslations();

function moveProfile(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.publishedProfiles.length) {
        return;
    }

    const ids = props.publishedProfiles.map((profile) => profile.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    router.patch(order().url, { ordered_ids: ids }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('administration.partners.title')" />

    <main class="flex flex-1 flex-col gap-8 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <div class="mt-1 flex items-center gap-3">
                <Building2 class="size-7 text-primary" aria-hidden="true" />
                <h1 class="text-3xl font-semibold tracking-tight">
                    {{ t('administration.partners.title') }}
                </h1>
            </div>
            <p class="mt-2 max-w-3xl text-muted-foreground">
                {{ t('administration.partners.description') }}
            </p>
        </header>

        <section aria-labelledby="pending-partners-title" class="space-y-4">
            <div>
                <h2 id="pending-partners-title" class="text-2xl font-semibold">
                    {{ t('administration.partners.pending_title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('administration.partners.pending_description') }}
                </p>
            </div>

            <p
                v-if="pendingRevisions.length === 0"
                class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
            >
                {{ t('administration.partners.pending_empty') }}
            </p>
            <PartnerModerationCard
                v-for="revision in pendingRevisions"
                :key="revision.id"
                :revision="revision"
            />
        </section>

        <section aria-labelledby="published-partners-title">
            <Card>
                <CardHeader>
                    <CardTitle id="published-partners-title">
                        {{ t('administration.partners.published_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('administration.partners.published_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="publishedProfiles.length === 0"
                        class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
                    >
                        {{ t('administration.partners.published_empty') }}
                    </p>
                    <ol v-else class="space-y-3">
                        <li
                            v-for="(profile, index) in publishedProfiles"
                            :key="profile.id"
                            class="flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center"
                        >
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-secondary font-semibold text-secondary-foreground"
                                aria-hidden="true"
                            >
                                {{ index + 1 }}
                            </span>
                            <img
                                v-if="profile.revision.imageUrl"
                                :src="profile.revision.imageUrl"
                                :alt="profile.revision.nameFr"
                                class="aspect-video w-full rounded-lg object-cover sm:w-28"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">
                                    {{ profile.revision.nameFr }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ profile.revision.nameEn }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-11"
                                    :disabled="index === 0"
                                    :aria-label="
                                        t('administration.partners.move_up', {
                                            name: profile.revision.nameFr,
                                        })
                                    "
                                    @click="moveProfile(index, -1)"
                                >
                                    <ArrowUp aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-11"
                                    :disabled="
                                        index === publishedProfiles.length - 1
                                    "
                                    :aria-label="
                                        t('administration.partners.move_down', {
                                            name: profile.revision.nameFr,
                                        })
                                    "
                                    @click="moveProfile(index, 1)"
                                >
                                    <ArrowDown aria-hidden="true" />
                                </Button>
                                <Form
                                    v-bind="unpublish.form(profile.id)"
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        class="min-h-11"
                                        data-test="unpublish-partner-profile"
                                        :disabled="processing"
                                        :aria-busy="
                                            processing ? 'true' : undefined
                                        "
                                    >
                                        <Spinner v-if="processing" />
                                        <EyeOff v-else aria-hidden="true" />
                                        {{
                                            processing
                                                ? t(
                                                      'administration.partners.unpublishing',
                                                  )
                                                : t(
                                                      'administration.partners.unpublish',
                                                  )
                                        }}
                                    </Button>
                                </Form>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
