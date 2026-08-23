<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
const lastDecision = ref<SwipeDecision | null>(null);
const matchDialogOpen = ref(props.match !== null);

function submit(decision: SwipeDecision): void {
    if (isSubmitting.value || !props.suggestion) {
        return;
    }

    isSubmitting.value = true;
    lastDecision.value = decision;
    errorMessage.value = null;

    router.post(
        swipe(props.suggestion.userId).url,
        { decision },
        {
            preserveScroll: true,
            onError: (errors) => {
                errorMessage.value = String(
                    errors.decision ??
                        errors.target ??
                        'Une erreur est survenue.',
                );
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function retry(): void {
    if (lastDecision.value) {
        submit(lastDecision.value);
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
        class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6"
    >
        <section class="space-y-2">
            <p class="text-sm font-medium text-primary">Découverte</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Découvrir des amis fans
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                Parcourez les profils proposés selon vos passions communes.
            </p>
        </section>

        <Alert
            v-if="errorMessage"
            variant="destructive"
            aria-live="assertive"
            class="max-w-2xl"
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

        <Card v-else-if="suggestion === null" class="w-full max-w-2xl">
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
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>C’est un match !</DialogTitle>
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
