<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import DemoConversation from '@/components/onboarding/DemoConversation.vue';
import DemoMatch from '@/components/onboarding/DemoMatch.vue';
import DemoSwipeCard from '@/components/onboarding/DemoSwipeCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as discovery } from '@/routes/discovery';
import { advance, complete, restart, skip } from '@/routes/onboarding';

type Step = 'pass_demo' | 'like_demo' | 'match_demo' | 'conversation_demo';
type DemoProfile = {
    displayName: string;
    bio: string;
    interests: string[];
    avatar: {
        name: string;
        imageUrl: string;
        primaryColor: string;
        secondaryColor: string;
    };
};

const props = defineProps<{
    status: 'in_progress';
    step: Step;
    resumable: boolean;
    demoProfiles: [DemoProfile, DemoProfile];
}>();

const busy = ref(false);
const wrongActionFeedback = ref<string | null>(null);
const feedbackKey = ref(0);
const stepInstruction = computed<Record<Step, string>>(() => ({
    pass_demo:
        'Pour découvrir comment écarter un profil qui ne vous correspond pas, choisissez Passer.',
    like_demo:
        'Aimez ce profil pour indiquer que vous souhaitez faire connaissance.',
    match_demo:
        'Lorsque deux membres s’aiment mutuellement, un match amical est créé.',
    conversation_demo: 'Envoyez une réponse fictive pour terminer le tutoriel.',
}));

watch(
    () => props.step,
    async () => {
        await nextTick();
        document.querySelector<HTMLElement>('[data-test$="-heading"]')?.focus();
    },
);

function submitStep(expected: Step): void {
    busy.value = true;
    router.patch(
        advance().url,
        { step: expected },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function decide(decision: 'pass' | 'like'): void {
    const required = props.step === 'pass_demo' ? 'pass' : 'like';

    if (decision !== required) {
        wrongActionFeedback.value =
            required === 'pass'
                ? 'Cette étape vous demande de passer ce profil.'
                : 'Cette étape vous demande d’aimer ce profil.';
        feedbackKey.value += 1;

        return;
    }

    wrongActionFeedback.value = null;
    submitStep(props.step);
}

function post(url: string): void {
    busy.value = true;
    router.post(
        url,
        {},
        {
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Démonstration" />
    <main
        class="mx-auto flex min-h-full w-full max-w-2xl flex-col items-center gap-5 overflow-y-auto px-4 py-[max(1rem,env(safe-area-inset-top))] sm:px-6"
    >
        <header class="flex w-full items-start justify-between gap-3">
            <div class="space-y-1">
                <Badge>Démonstration</Badge>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Découvrez le fonctionnement
                </h1>
            </div>
            <Button as-child variant="ghost" size="sm">
                <Link :href="discovery()">Continuer plus tard</Link>
            </Button>
        </header>

        <p class="w-full text-center text-sm text-muted-foreground">
            Cette démonstration est entièrement fictive : aucun J’aime, match ou
            message réel ne sera envoyé.
        </p>

        <p
            class="w-full rounded-2xl bg-secondary px-4 py-3 text-center font-medium"
            aria-live="polite"
        >
            {{ stepInstruction[step] }}
        </p>
        <p
            v-if="wrongActionFeedback"
            :key="feedbackKey"
            role="alert"
            class="w-full rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-center text-sm font-medium text-destructive"
        >
            {{ wrongActionFeedback }}
        </p>

        <DemoSwipeCard
            v-if="step === 'pass_demo'"
            :profile="demoProfiles[0]"
            required-decision="pass"
            :locked="busy"
            @pass="decide('pass')"
            @like="decide('like')"
        />
        <DemoSwipeCard
            v-else-if="step === 'like_demo'"
            :profile="demoProfiles[1]"
            required-decision="like"
            :locked="busy"
            @pass="decide('pass')"
            @like="decide('like')"
        />
        <DemoMatch
            v-else-if="step === 'match_demo'"
            :display-name="demoProfiles[1].displayName"
            :locked="busy"
            @open-conversation="submitStep('match_demo')"
        />
        <DemoConversation
            v-else
            :display-name="demoProfiles[1].displayName"
            :locked="busy"
            @complete="post(complete().url)"
        />

        <footer
            class="flex w-full flex-wrap justify-center gap-2 border-t pt-4"
        >
            <Button
                v-if="resumable && step !== 'pass_demo'"
                type="button"
                variant="outline"
                :disabled="busy"
                @click="post(restart().url)"
            >
                Recommencer
            </Button>
            <Dialog>
                <DialogTrigger as-child>
                    <Button type="button" variant="ghost" :disabled="busy">
                        Ignorer le tutoriel
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >Quitter le tutoriel maintenant ?</DialogTitle
                        >
                        <DialogDescription>
                            Continuer plus tard conserve votre progression.
                            Ignorer le tutoriel le marque comme passé, mais vous
                            pourrez toujours le relancer depuis les réglages.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="busy"
                            data-test="confirm-skip-onboarding"
                            @click="post(skip().url)"
                        >
                            Ignorer le tutoriel
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </footer>
    </main>
</template>
