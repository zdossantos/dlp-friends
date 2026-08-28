<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { index as discoveryIndex } from '@/routes/discovery';
import type { PublicMember, VisitFrequency } from '@/types';

const props = defineProps<{ member: PublicMember }>();
const { t } = useTranslations();
const frequencyKeys: Record<
    VisitFrequency,
    `blocking.frequency_${VisitFrequency}`
> = {
    rarely: 'blocking.frequency_rarely',
    sometimes: 'blocking.frequency_sometimes',
    often: 'blocking.frequency_often',
    very_often: 'blocking.frequency_very_often',
};
const visitFrequency = computed(() =>
    props.member.visit_frequency
        ? t(frequencyKeys[props.member.visit_frequency])
        : t('blocking.frequency_unknown'),
);
</script>

<template>
    <Head :title="member.display_name" />
    <main
        data-test="public-member-profile"
        class="mx-auto w-full max-w-3xl p-4 sm:p-8"
    >
        <Button as-child variant="ghost" class="mb-4">
            <Link :href="discoveryIndex().url">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('navigation.discovery') }}
            </Link>
        </Button>

        <Card class="overflow-hidden rounded-3xl">
            <CardContent class="grid gap-6 p-6 sm:grid-cols-[14rem_1fr] sm:p-8">
                <AvatarPortrait :avatar="member.avatar" class="w-full" />
                <div class="space-y-6">
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight">
                                {{ member.display_name }}
                            </h1>
                            <Badge variant="secondary" class="mt-2">
                                {{ t('blocking.age', { age: member.age }) }}
                            </Badge>
                        </div>
                        <BlockMemberDialog :member-id="member.id" />
                    </div>

                    <section>
                        <h2 class="mb-2 font-semibold">
                            {{ t('blocking.about') }}
                        </h2>
                        <p class="leading-7 text-muted-foreground">
                            {{ member.bio ?? t('blocking.empty_bio') }}
                        </p>
                    </section>

                    <section>
                        <h2 class="mb-2 flex items-center gap-2 font-semibold">
                            <CalendarDays
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {{ t('blocking.visit_frequency') }}
                        </h2>
                        <p class="text-muted-foreground">
                            {{ visitFrequency }}
                        </p>
                    </section>

                    <section v-if="member.interests.length > 0">
                        <h2 class="mb-3 flex items-center gap-2 font-semibold">
                            <Sparkles
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {{ t('blocking.interests') }}
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="interest in member.interests"
                                :key="interest.id"
                                variant="outline"
                            >
                                {{ interest.name }}
                            </Badge>
                        </div>
                    </section>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
