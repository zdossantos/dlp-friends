<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { show as showConversation } from '@/routes/conversations';
import { swipe } from '@/routes/discovery';
import { show as showProfile } from '@/routes/member-profile';
import type { DiscoveryMatch, DiscoveryProfile, SwipeDecision } from '@/types';

const props = defineProps<{
    suggestions?: DiscoveryProfile[];
    match: DiscoveryMatch | null;
}>();

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
                        'Une erreur est survenue.',
                );
            },
            onHttpException: () => {
                errorMessage.value =
                    'Le serveur n’a pas pu enregistrer cette décision.';

                return false;
            },
            onNetworkError: () => {
                errorMessage.value =
                    'La connexion a échoué avant l’enregistrement de cette décision.';

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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Découvrir', href: { url: '/discover' } }],
    },
});
</script>

<template>
    <Head title="Découvrir" />

    <main
        data-test="discovery-page"
        class="mx-auto flex h-full min-h-0 w-full max-w-md flex-1 flex-col gap-3 overflow-visible px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:pt-6"
    >
        <section class="shrink-0 space-y-0.5">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Découvrir
            </h1>
            <p class="text-sm leading-5 text-muted-foreground">
                Des membres qui partagent vos intérêts.
            </p>
        </section>

        <Alert
            v-if="errorMessage"
            variant="destructive"
            aria-live="assertive"
            class="w-full"
        >
            <AlertTitle>Décision non enregistrée</AlertTitle>
            <AlertDescription class="space-y-3">
                <p>{{ errorMessage }}</p>
                <Button
                    type="button"
                    variant="outline"
                    aria-label="Réessayer"
                    :disabled="isSubmitting"
                    @click="retry"
                >
                    Réessayer
                </Button>
            </AlertDescription>
        </Alert>

        <section
            v-if="suggestions === undefined"
            class="grid w-full max-w-md gap-4"
            aria-busy="true"
        >
            <p class="text-sm font-medium text-muted-foreground">
                Recherche de profils…
            </p>
            <Skeleton class="h-12 w-32" />
            <Skeleton class="h-64 w-full" />
            <Skeleton class="h-10 w-full" />
        </section>

        <Card v-else-if="suggestions.length === 0" class="w-full rounded-3xl">
            <CardHeader>
                <CardTitle>
                    Vous avez exploré tous les profils disponibles
                </CardTitle>
                <CardDescription>
                    Revenez plus tard ou ajustez votre profil pour mieux
                    représenter vos intérêts.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button as-child variant="outline">
                    <Link :href="showProfile()">Mon profil</Link>
                </Button>
            </CardContent>
        </Card>

        <section
            v-else
            class="relative min-h-0 w-full flex-1 pb-3"
            aria-label="Profils à découvrir"
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
                    @like="index === 0 && submit('like')"
                    @pass="index === 0 && submit('pass')"
                />
            </div>
        </section>

        <Dialog v-if="match && matchDialogOpen" v-model:open="matchDialogOpen">
            <DialogContent
                class="border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950"
            >
                <DialogHeader>
                    <DialogTitle class="text-amber-900 dark:text-amber-100">
                        C’est un match !
                    </DialogTitle>
                    <DialogDescription>
                        {{ match.displayName }} a aussi aimé votre profil. Vous
                        pouvez maintenant commencer à échanger.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button as-child variant="outline">
                        <Link
                            :href="showConversation(match.conversationId)"
                            data-test="open-match-conversation"
                        >
                            Ouvrir la conversation
                        </Link>
                    </Button>
                    <Button
                        type="button"
                        aria-label="Continuer à découvrir"
                        @click="matchDialogOpen = false"
                    >
                        Continuer à découvrir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>
