<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MatchDialog from '@/components/discovery/MatchDialog.vue';
import SwipeCard from '@/components/discovery/SwipeCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import { show as showConversation } from '@/routes/conversations';
import { swipe } from '@/routes/discovery';
import { show as showProfile } from '@/routes/member-profile';
import { show as showMember } from '@/routes/members';
import type { DiscoveryMatch, DiscoveryProfile, SwipeDecision } from '@/types';

const props = defineProps<{
    suggestions?: DiscoveryProfile[];
    match: DiscoveryMatch | null;
}>();
const { t } = useTranslations();
setLayoutProps({
    breadcrumbs: [
        { title: t('discovery.navigation'), href: { url: '/discover' } },
    ],
});

const activeSuggestion = computed(() => props.suggestions?.[0]);

const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const retryAttempt = ref<{
    targetUserId: number;
    decision: SwipeDecision;
} | null>(null);
const visibleMatchId = ref(props.match?.id ?? null);
const matchDialogOpen = ref(props.match !== null);

watch(
    () => props.match?.id ?? null,
    (matchId) => {
        if (matchId === null) {
            matchDialogOpen.value = false;

            return;
        }

        if (matchId !== visibleMatchId.value) {
            visibleMatchId.value = matchId;
            matchDialogOpen.value = true;
        }
    },
);

watch(
    () => props.suggestions,
    (suggestions) => {
        if (suggestions === undefined) {
            return;
        }

        const targetUserId = suggestions[0]?.userId ?? null;

        if (
            retryAttempt.value !== null &&
            retryAttempt.value.targetUserId !== targetUserId
        ) {
            retryAttempt.value = null;
            errorMessage.value = null;
        }
    },
);

function submit(decision: SwipeDecision, targetUserId?: number): void {
    const resolvedTargetUserId = targetUserId ?? activeSuggestion.value?.userId;

    if (isSubmitting.value || resolvedTargetUserId === undefined) {
        return;
    }

    isSubmitting.value = true;
    retryAttempt.value = { targetUserId: resolvedTargetUserId, decision };
    errorMessage.value = null;

    router.post(
        swipe(resolvedTargetUserId).url,
        { decision },
        {
            only: ['suggestions', 'match'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                retryAttempt.value = null;
            },
            onError: (errors) => {
                errorMessage.value = String(
                    errors.decision ??
                        errors.target ??
                        t('discovery.page.generic_error'),
                );
            },
            onHttpException: () => {
                errorMessage.value = t('discovery.page.server_error');

                return false;
            },
            onNetworkError: () => {
                errorMessage.value = t('discovery.page.network_error');

                return false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function retry(): void {
    if (retryAttempt.value) {
        submit(retryAttempt.value.decision, retryAttempt.value.targetUserId);
    }
}
</script>

<template>
    <Head :title="t('discovery.page.title')" />

    <main
        data-test="discovery-page"
        class="mx-auto flex h-full min-h-0 w-full max-w-md flex-1 flex-col gap-3 overflow-visible px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:pt-6"
    >
        <section class="shrink-0 space-y-0.5">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ t('discovery.page.title') }}
            </h1>
            <p class="text-sm leading-5 text-muted-foreground">
                {{ t('discovery.page.description') }}
            </p>
        </section>

        <Alert
            v-if="errorMessage"
            variant="destructive"
            aria-live="assertive"
            class="w-full"
        >
            <AlertTitle>{{ t('discovery.page.error_title') }}</AlertTitle>
            <AlertDescription class="space-y-3">
                <p>{{ errorMessage }}</p>
                <Button
                    type="button"
                    variant="outline"
                    :aria-label="t('discovery.page.retry')"
                    :disabled="isSubmitting"
                    @click="retry"
                >
                    {{ t('discovery.page.retry') }}
                </Button>
            </AlertDescription>
        </Alert>

        <section
            v-if="suggestions === undefined"
            class="grid w-full max-w-md gap-4"
            aria-busy="true"
        >
            <p class="text-sm font-medium text-muted-foreground">
                {{ t('discovery.page.loading') }}
            </p>
            <Skeleton class="h-12 w-32" />
            <Skeleton class="h-64 w-full" />
            <Skeleton class="h-10 w-full" />
        </section>

        <Card v-else-if="suggestions.length === 0" class="w-full rounded-3xl">
            <CardHeader>
                <CardTitle>
                    {{ t('discovery.page.empty_title') }}
                </CardTitle>
                <CardDescription>
                    {{ t('discovery.page.empty_description') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button as-child variant="outline">
                    <Link :href="showProfile()">{{
                        t('discovery.page.profile')
                    }}</Link>
                </Button>
            </CardContent>
        </Card>

        <section
            v-else
            class="relative min-h-0 w-full flex-1 pb-3"
            :aria-label="t('discovery.page.profiles_label')"
        >
            <div
                v-for="(profile, index) in suggestions"
                :key="profile.userId"
                data-test="discovery-card-stack-item"
                class="absolute inset-x-0 top-0 bottom-3 flex w-full justify-center transition-transform duration-300 ease-out"
                :class="index === 0 ? undefined : 'pointer-events-none'"
                :style="{
                    zIndex: suggestions.length - index,
                    transform:
                        index === 0
                            ? undefined
                            : `translateY(${index * 8}px) scale(${1 - index * 0.015})`,
                }"
                :aria-hidden="index > 0 ? 'true' : undefined"
                :inert="index > 0"
            >
                <SwipeCard
                    :profile="profile"
                    :locked="isSubmitting || index > 0"
                    :preview="index > 0"
                    :public-profile-href="showMember(profile.userId).url"
                    @like="index === 0 && submit('like')"
                    @pass="index === 0 && submit('pass')"
                    @open="
                        index === 0 &&
                        router.visit(showMember(profile.userId).url)
                    "
                />
            </div>
        </section>

        <MatchDialog
            v-if="match"
            v-model:open="matchDialogOpen"
            :match="match"
            :conversation-href="showConversation(match.conversationId).url"
        />
    </main>
</template>
