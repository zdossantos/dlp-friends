<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Eye, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import SwipeCard from '@/components/discovery/SwipeCard.vue';
import InputError from '@/components/InputError.vue';
import AvatarCarousel from '@/components/profile/AvatarCarousel.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import InterestTagSelector from '@/components/profile/InterestTagSelector.vue';
import ProfileFormStepper from '@/components/profile/ProfileFormStepper.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type {
    AvatarOption,
    DiscoveryProfile,
    InterestOption,
    Profile,
    VisitFrequency,
} from '@/types';

type Option = { value: string; label: string };

const { t } = useTranslations();
const profileStepLabels = computed(() => [
    t('profile.form.steps.avatar'),
    t('profile.form.steps.identity'),
    t('profile.form.steps.affinities'),
    t('profile.form.steps.preview'),
]);

const props = defineProps<{
    profile: Profile | null;
    action: string;
    method: 'post' | 'patch';
    submitLabel: string;
    visitFrequencies: Option[];
    visibilities: Option[];
    avatars: AvatarOption[];
    interests: InterestOption[];
    selectedInterestIds: number[];
    interestLimit: number;
    age: number;
}>();

const currentStep = ref(1);
const furthestStep = ref(1);
const avatarId = ref<number | null>(
    props.profile?.avatar_id ?? props.avatars[0]?.id ?? null,
);
const displayName = ref(props.profile?.display_name ?? '');
const bio = ref(props.profile?.bio ?? '');
const visitFrequency = ref(props.profile?.visit_frequency ?? '');
const visibility = ref(props.profile?.visibility ?? 'visible');
const interestIds = ref([...props.selectedInterestIds]);

const selectedAvatar = computed(
    () => props.avatars.find((avatar) => avatar.id === avatarId.value) ?? null,
);
const selectedInterests = computed(() =>
    props.interests.filter((interest) =>
        interestIds.value.includes(interest.id),
    ),
);
const frequencyDescriptionKeys = {
    rarely: 'profile.form.frequency_descriptions.rarely',
    sometimes: 'profile.form.frequency_descriptions.sometimes',
    often: 'profile.form.frequency_descriptions.often',
    very_often: 'profile.form.frequency_descriptions.very_often',
} as const;
const frequencyDescriptions: Record<string, string> = {
    rarely: t(frequencyDescriptionKeys.rarely),
    sometimes: t(frequencyDescriptionKeys.sometimes),
    often: t(frequencyDescriptionKeys.often),
    very_often: t(frequencyDescriptionKeys.very_often),
};
const previewProfile = computed<DiscoveryProfile | null>(() => {
    if (selectedAvatar.value === null) {
        return null;
    }

    return {
        userId: 0,
        profileId: 0,
        displayName: displayName.value || t('profile.form.preview_name'),
        isAdmin: false,
        avatar: selectedAvatar.value,
        age: props.age,
        bio: bio.value || null,
        visitFrequency: (visitFrequency.value || null) as VisitFrequency | null,
        commonInterestCount: selectedInterests.value.length,
        commonInterests: selectedInterests.value.map(
            (interest) => interest.name,
        ),
        interests: selectedInterests.value.map((interest) => ({
            name: interest.name,
            isCommon: true,
        })),
        frequencyBonus: false,
        score: 0,
    };
});
const canContinue = computed(() => {
    if (currentStep.value === 1) {
        return avatarId.value !== null;
    }

    if (currentStep.value === 2) {
        return displayName.value.trim().length > 0;
    }

    if (currentStep.value === 3) {
        return visitFrequency.value !== '';
    }

    return true;
});

function goTo(step: number): void {
    currentStep.value = Math.min(4, Math.max(1, step));
    furthestStep.value = Math.max(furthestStep.value, currentStep.value);
    requestAnimationFrame(() =>
        document
            .querySelector<HTMLElement>('[data-test="profile-step-title"]')
            ?.focus(),
    );
}

function next(): void {
    goTo(currentStep.value + 1);
}

function previous(): void {
    goTo(currentStep.value - 1);
}

function showInvalidStep(errors: Record<string, string>): void {
    if (errors.avatar_id) {
        goTo(1);
    } else if (errors.display_name || errors.bio) {
        goTo(2);
    } else if (errors.interest_ids || errors.visit_frequency) {
        goTo(3);
    } else if (errors.visibility) {
        goTo(4);
    }
}
</script>

<template>
    <Form
        :action="action"
        :method="method"
        class="flex h-full min-h-0 flex-col gap-3"
        v-slot="{ errors, processing }"
        @error="showInvalidStep"
    >
        <ProfileFormStepper
            :labels="profileStepLabels"
            :current-step="currentStep"
            :furthest-step="furthestStep"
            @select="goTo"
        />

        <section
            v-show="currentStep === 1"
            class="flex min-h-0 flex-1 flex-col gap-2"
            aria-labelledby="profile-step-1-title"
        >
            <div class="shrink-0 text-center">
                <h2
                    id="profile-step-1-title"
                    data-test="profile-step-title"
                    class="text-xl font-semibold tracking-tight outline-none sm:text-2xl"
                    tabindex="-1"
                >
                    {{ t('profile.form.avatar_title') }}
                </h2>
                <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                    {{ t('profile.form.avatar_description') }}
                </p>
            </div>

            <AvatarCarousel v-model="avatarId" :avatars="avatars" />
            <p
                v-if="avatars.length === 0"
                class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            >
                {{ t('profile.form.avatar_empty') }}
            </p>
            <InputError :message="errors.avatar_id" />
        </section>

        <section
            v-show="currentStep === 2"
            data-test="profile-step-content-2"
            class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-1 pb-2"
            aria-labelledby="profile-step-2-title"
        >
            <div>
                <h2
                    id="profile-step-2-title"
                    data-test="profile-step-title"
                    class="text-xl font-semibold tracking-tight outline-none sm:text-2xl"
                    tabindex="-1"
                >
                    {{ t('profile.form.identity_title') }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('profile.form.identity_description') }}
                </p>
            </div>

            <div
                v-if="selectedAvatar"
                class="flex items-center gap-3 rounded-2xl border bg-muted/30 p-2"
            >
                <AvatarPortrait
                    :avatar="selectedAvatar"
                    class="size-12 shrink-0 rounded-xl"
                />
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('profile.form.preview_label') }}
                    </p>
                    <p class="font-semibold">{{ selectedAvatar.name }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="display_name">{{
                    t('profile.form.display_name')
                }}</Label>
                <Input
                    id="display_name"
                    v-model="displayName"
                    name="display_name"
                    autocomplete="nickname"
                    required
                    maxlength="80"
                />
                <InputError :message="errors.display_name" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="bio"
                        >{{ t('profile.form.bio') }}
                        <span class="text-muted-foreground">{{
                            t('profile.form.optional')
                        }}</span></Label
                    >
                    <span class="text-xs text-muted-foreground"
                        >{{ bio.length }} / 500</span
                    >
                </div>
                <textarea
                    id="bio"
                    v-model="bio"
                    name="bio"
                    maxlength="500"
                    class="min-h-24 w-full resize-none rounded-2xl border border-input bg-background px-4 py-3 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-32"
                />
                <InputError :message="errors.bio" />
            </div>
        </section>

        <section
            v-show="currentStep === 3"
            data-test="profile-step-content-3"
            class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-1 pb-2"
            aria-labelledby="profile-step-3-title"
        >
            <div>
                <h2
                    id="profile-step-3-title"
                    data-test="profile-step-title"
                    class="text-xl font-semibold tracking-tight outline-none sm:text-2xl"
                    tabindex="-1"
                >
                    {{ t('profile.form.affinities_title') }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('profile.form.affinities_description') }}
                </p>
            </div>

            <div class="grid gap-2">
                <InterestTagSelector
                    v-model:selected-ids="interestIds"
                    :interests="interests"
                    :limit="interestLimit"
                />
                <InputError :message="errors.interest_ids" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="font-medium">
                    {{ t('profile.form.visit_frequency') }}
                </legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="option in visitFrequencies"
                        :key="option.value"
                        class="relative flex min-h-14 cursor-pointer flex-col justify-center rounded-2xl border px-3 py-2 transition-[border-color,background-color,transform] focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 motion-reduce:transition-none"
                        :class="
                            visitFrequency === option.value
                                ? 'border-primary bg-primary/10 text-foreground'
                                : 'border-input bg-background hover:border-primary/50 hover:bg-accent/50'
                        "
                    >
                        <input
                            v-model="visitFrequency"
                            class="sr-only"
                            type="radio"
                            name="visit_frequency"
                            :value="option.value"
                            required
                        />
                        <span class="pr-5 text-sm font-semibold">{{
                            option.label
                        }}</span>
                        <span
                            class="text-[0.68rem] leading-4 text-muted-foreground"
                        >
                            {{ frequencyDescriptions[option.value] }}
                        </span>
                        <Check
                            v-if="visitFrequency === option.value"
                            class="absolute top-2.5 right-2.5 size-4 text-primary"
                            aria-hidden="true"
                        />
                    </label>
                </div>
                <InputError :message="errors.visit_frequency" />
            </fieldset>
        </section>

        <section
            v-show="currentStep === 4"
            data-test="profile-step-content-4"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-1 pb-2"
            aria-labelledby="profile-step-4-title"
        >
            <div>
                <h2
                    id="profile-step-4-title"
                    data-test="profile-step-title"
                    class="text-xl font-semibold tracking-tight outline-none sm:text-2xl"
                    tabindex="-1"
                >
                    {{ t('profile.form.preview_title') }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('profile.form.preview_description') }}
                </p>
            </div>

            <div
                v-if="previewProfile"
                data-test="profile-preview"
                class="mx-auto w-full max-w-sm"
            >
                <SwipeCard
                    :profile="previewProfile"
                    :locked="true"
                    :preview="true"
                    :compact="true"
                />
            </div>

            <div class="grid gap-3">
                <Label for="visibility">{{
                    t('profile.form.visibility')
                }}</Label>
                <input type="hidden" name="visibility" :value="visibility" />
                <Select v-model="visibility" required>
                    <SelectTrigger
                        id="visibility"
                        class="h-14 w-full rounded-2xl bg-background px-4"
                        aria-describedby="visibility-error"
                        :aria-invalid="errors.visibility ? 'true' : undefined"
                    >
                        <Eye class="size-5 text-primary" aria-hidden="true" />
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        <SelectItem
                            v-for="option in visibilities"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div id="visibility-error">
                    <InputError :message="errors.visibility" />
                </div>
            </div>

            <p
                class="hidden gap-3 rounded-2xl bg-primary/5 p-4 text-sm text-muted-foreground sm:flex"
            >
                <Sparkles
                    class="size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                {{ t('profile.form.editable') }}
            </p>
        </section>

        <footer
            data-test="profile-form-footer"
            class="z-30 -mx-1 flex shrink-0 items-center gap-3 px-1 pt-3 pb-1"
        >
            <Button
                v-if="currentStep > 1"
                data-test="profile-back-button"
                type="button"
                variant="outline"
                class="min-h-12 rounded-full border-transparent bg-background px-4 shadow-sm hover:bg-accent"
                @click="previous"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('profile.actions.previous') }}
            </Button>
            <span v-else class="flex-1" />

            <Button
                v-if="currentStep < 4"
                type="button"
                :disabled="!canContinue"
                class="ml-auto min-h-12 flex-1 rounded-full bg-gradient-to-r from-primary to-primary/75 sm:max-w-64"
                @click="next"
            >
                {{ t('profile.actions.next') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Button>
            <Button
                v-else
                type="submit"
                :disabled="processing || avatars.length === 0"
                :aria-busy="processing ? 'true' : undefined"
                class="ml-auto min-h-12 flex-1 rounded-full bg-gradient-to-r from-primary to-primary/75 sm:max-w-72"
            >
                <Spinner v-if="processing" />
                <Check v-else class="size-4" aria-hidden="true" />
                {{ submitLabel }}
            </Button>
        </footer>
    </Form>
</template>
