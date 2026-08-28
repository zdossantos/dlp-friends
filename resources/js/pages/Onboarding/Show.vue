<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConversationHeader from '@/components/conversations/ConversationHeader.vue';
import MessageComposer from '@/components/conversations/MessageComposer.vue';
import MessageItems from '@/components/conversations/MessageItems.vue';
import MatchDialog from '@/components/discovery/MatchDialog.vue';
import SwipeCard from '@/components/discovery/SwipeCard.vue';
import ProfileFormStepper from '@/components/profile/ProfileFormStepper.vue';
import { useTranslations } from '@/composables/useTranslations';
import { advance, complete } from '@/routes/onboarding';
import type {
    ConversationMessage,
    ConversationParticipant,
    DiscoveryCardProfile,
} from '@/types';

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
    demoProfiles: [DemoProfile, DemoProfile];
}>();
const { t } = useTranslations();

const busy = ref(false);
const tutorialMessages = ref<ConversationMessage[]>([
    {
        id: 1,
        conversation_id: 0,
        author_user_id: 0,
        content: t('onboarding.initial_message'),
        read_at: null,
        created_at: null,
    },
]);
const registrationStepLabels = computed(() => [
    t('onboarding.step_avatar'),
    t('onboarding.step_identity'),
    t('onboarding.step_affinities'),
    t('onboarding.step_preview'),
    t('onboarding.step_pass'),
    t('onboarding.step_like'),
    t('onboarding.step_match'),
    t('onboarding.step_conversation'),
]);
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
    () => [
        toSwipeProfile(props.demoProfiles[0], 28),
        toSwipeProfile(props.demoProfiles[1], 31),
    ],
);
const tutorialParticipant = computed<ConversationParticipant>(() => ({
    id: 0,
    display_name: props.demoProfiles[1].displayName,
    avatar: {
        id: 0,
        name: props.demoProfiles[1].avatar.name,
        image_url: props.demoProfiles[1].avatar.imageUrl,
        primary_color: props.demoProfiles[1].avatar.primaryColor,
        secondary_color: props.demoProfiles[1].avatar.secondaryColor,
    },
}));

function toSwipeProfile(
    profile: DemoProfile,
    age: number,
): DiscoveryCardProfile {
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
    pass_demo: t('onboarding.pass_instruction'),
    like_demo: t('onboarding.like_instruction'),
    match_demo: t('onboarding.match_instruction'),
    conversation_demo: t('onboarding.conversation_instruction'),
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
            onError: () => {
                toast.error(t('onboarding.step_error'));
            },
        },
    );
}

function decide(decision: 'pass' | 'like'): void {
    const required = props.step === 'pass_demo' ? 'pass' : 'like';

    if (decision !== required) {
        toast.error(
            required === 'pass'
                ? t('onboarding.reject_instruction')
                : t('onboarding.like_required'),
        );

        return;
    }

    submitStep(props.step);
}

function completeWithMessage(content: string): Promise<ConversationMessage> {
    return new Promise((resolve, reject) => {
        const message: ConversationMessage = {
            id: 2,
            conversation_id: 0,
            author_user_id: -1,
            content,
            read_at: null,
            created_at: null,
        };

        busy.value = true;
        router.post(
            complete().url,
            {},
            {
                onSuccess: () => resolve(message),
                onError: () => {
                    toast.error(t('onboarding.message_error'));
                    reject(new Error('Unable to complete onboarding.'));
                },
                onFinish: () => {
                    busy.value = false;
                },
            },
        );
    });
}
</script>

<template>
    <Head :title="t('onboarding.page_title')" />
    <main
        class="relative mx-auto flex min-h-full w-full max-w-2xl flex-col items-center gap-5 overflow-y-auto px-4 py-[max(1rem,env(safe-area-inset-top))] sm:px-6"
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
        <DotLottieVue
            v-if="['pass_demo', 'like_demo'].includes(step)"
            :class="step === 'like_demo' ? 'scale-x-[-1]' : ''"
            class="pointer-events-none absolute top-1/2 left-1/2 z-50 size-52 -translate-x-1/2 -translate-y-1/2"
            autoplay
            :speed="0.9"
            loop
            src="https://lottie.host/a3e34c03-f307-482b-a419-703ada211358/Ye6Cz6TKop.lottie"
        />
        <SwipeCard
            v-if="step === 'pass_demo'"
            :profile="swipeProfiles[0]"
            allowed-decision="pass"
            :locked="busy"
            compact
            swipe-anime="left"
            @pass="decide('pass')"
            @like="decide('like')"
        />
        <SwipeCard
            v-else-if="step === 'like_demo'"
            :profile="swipeProfiles[1]"
            allowed-decision="like"
            :locked="busy"
            compact
            swipe-anime="right"
            @pass="decide('pass')"
            @like="decide('like')"
        />
        <MatchDialog
            v-else-if="step === 'match_demo'"
            :open="true"
            :match="{ displayName: demoProfiles[1].displayName }"
            :dismissible="false"
            :show-continue="false"
            :locked="busy"
            @open-conversation="submitStep('match_demo')"
        />
        <section
            v-else
            class="flex min-h-[32rem] w-full flex-1 flex-col overflow-hidden rounded-3xl border bg-background shadow-sm"
        >
            <ConversationHeader :participant="tutorialParticipant" />
            <section
                role="log"
                :aria-label="t('onboarding.message_history')"
                aria-relevant="additions text"
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6"
            >
                <ol role="list" class="flex flex-col gap-2">
                    <MessageItems
                        :messages="tutorialMessages"
                        :current-user-id="-1"
                    />
                </ol>
            </section>
            <MessageComposer
                :archived="false"
                :on-sent="(message) => tutorialMessages.push(message)"
                :submit-message="completeWithMessage"
            />
        </section>
    </main>
</template>
