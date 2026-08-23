<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import { swipe } from '@/routes/discovery';
import { show as showProfile } from '@/routes/member-profile';
import type { DiscoveryMatch, DiscoveryProfile, SwipeDecision } from '@/types';

const props = defineProps<{
    suggestion?: DiscoveryProfile | null;
    match: DiscoveryMatch | null;
}>();

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
    () => props.suggestion,
    (suggestion) => {
        if (suggestion === undefined) {
            return;
        }

        const targetUserId = suggestion?.userId ?? null;

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
    const resolvedTargetUserId = targetUserId ?? props.suggestion?.userId;

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
            preserveScroll: true,
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
        class="mx-auto flex w-full max-w-md flex-1 flex-col gap-4 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8"
    >
        <section class="space-y-1">
            <h1 class="text-3xl font-semibold tracking-tight">Découvrir</h1>
            <p class="text-sm leading-6 text-muted-foreground">
                Des membres qui partagent vos passions.
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
            v-if="suggestion === undefined"
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

        <Card v-else-if="suggestion === null" class="w-full rounded-3xl">
            <CardHeader>
                <CardTitle>
                    Vous avez exploré tous les profils disponibles
                </CardTitle>
                <CardDescription>
                    Revenez plus tard ou ajustez votre profil pour mieux
                    représenter vos passions.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button as-child variant="outline">
                    <Link :href="showProfile()">Mon profil</Link>
                </Button>
            </CardContent>
        </Card>

        <SwipeCard
            v-else
            :profile="suggestion"
            :locked="isSubmitting"
            @like="submit('like')"
            @pass="submit('pass')"
        />

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
                        pouvez continuer à découvrir d’autres membres.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
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
