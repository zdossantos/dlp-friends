<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import SwipeCard from '@/components/discovery/SwipeCard.vue';
import DemoConversation from '@/components/onboarding/DemoConversation.vue';
import DemoMatch from '@/components/onboarding/DemoMatch.vue';
import ProfileFormStepper from '@/components/profile/ProfileFormStepper.vue';
import { advance, complete } from '@/routes/onboarding';
import type { DiscoveryCardProfile } from '@/types';

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
const registrationStepLabels = [
    'Avatar',
    'Identité',
    'Affinités',
    'Aperçu',
    'Passer',
    'J’aime',
    'Match',
    'Discussion',
] as const;
const currentRegistrationStep = computed(
    () =>
        ({
            pass_demo: 5,
            like_demo: 6,
            match_demo: 7,
            conversation_demo: 8,
        })[props.step],
);
const swipeProfiles = computed<[DiscoveryCardProfile, DiscoveryCardProfile]>(
    () => [toSwipeProfile(props.demoProfiles[0], 28), toSwipeProfile(props.demoProfiles[1], 31)],
);

function toSwipeProfile(profile: DemoProfile, age: number): DiscoveryCardProfile {
    return {
        displayName: profile.displayName,
        age,
        bio: profile.bio,
        visitFrequency: null,
        commonInterestCount: 0,
        commonInterests: [],
        interests: profile.interests.map((name) => ({
            name,
            isCommon: false,
        })),
        frequencyBonus: false,
        avatar: {
            name: profile.avatar.name,
            image_url: profile.avatar.imageUrl,
            primary_color: profile.avatar.primaryColor,
            secondary_color: profile.avatar.secondaryColor,
        },
    };
}
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
    <Head title="Prise en main" />
    <main
        class="mx-auto flex min-h-full w-full max-w-2xl flex-col items-center gap-5 overflow-y-auto px-4 py-[max(1rem,env(safe-area-inset-top))] sm:px-6"
    >
        <ProfileFormStepper
            class="w-full"
            :labels="registrationStepLabels"
            :current-step="currentRegistrationStep"
            :furthest-step="currentRegistrationStep"
            :selectable="false"
        />

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

        <SwipeCard
            v-if="step === 'pass_demo'"
            :profile="swipeProfiles[0]"
            allowed-decision="pass"
            :locked="busy"
            compact
            @pass="decide('pass')"
            @like="decide('like')"
        />
        <SwipeCard
            v-else-if="step === 'like_demo'"
            :profile="swipeProfiles[1]"
            allowed-decision="like"
            :locked="busy"
            compact
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

    </main>
</template>
