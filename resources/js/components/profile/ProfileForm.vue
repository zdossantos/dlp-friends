<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Eye, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AvatarCarousel from '@/components/profile/AvatarCarousel.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import InterestTagSelector from '@/components/profile/InterestTagSelector.vue';
import ProfileFormStepper from '@/components/profile/ProfileFormStepper.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { AvatarOption, InterestOption, Profile } from '@/types';

type Option = { value: string; label: string };

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
const frequencyLabel = computed(
    () =>
        props.visitFrequencies.find(
            (option) => option.value === visitFrequency.value,
        )?.label ?? 'À choisir',
);
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
        class="space-y-6"
        v-slot="{ errors, processing }"
        @error="showInvalidStep"
    >
        <ProfileFormStepper
            :current-step="currentStep"
            :furthest-step="furthestStep"
            @select="goTo"
        />

        <section
            v-show="currentStep === 1"
            class="space-y-5"
            aria-labelledby="profile-step-1-title"
        >
            <div class="text-center">
                <h2
                    id="profile-step-1-title"
                    data-test="profile-step-title"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                    tabindex="-1"
                >
                    Votre avatar
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Choisissez le personnage qui vous ressemble.
                </p>
            </div>

            <AvatarCarousel v-model="avatarId" :avatars="avatars" />
            <p
                v-if="avatars.length === 0"
                class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            >
                Aucun avatar n’est disponible pour le moment.
            </p>
            <InputError :message="errors.avatar_id" />
        </section>

        <section
            v-show="currentStep === 2"
            class="space-y-6"
            aria-labelledby="profile-step-2-title"
        >
            <div>
                <h2
                    id="profile-step-2-title"
                    data-test="profile-step-title"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                    tabindex="-1"
                >
                    Votre identité
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Parlez un peu de vous et personnalisez votre profil.
                </p>
            </div>

            <div
                v-if="selectedAvatar"
                class="flex items-center gap-4 rounded-2xl border bg-muted/30 p-3"
            >
                <AvatarPortrait
                    :avatar="selectedAvatar"
                    class="size-16 shrink-0 rounded-2xl"
                />
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Aperçu
                    </p>
                    <p class="font-semibold">{{ selectedAvatar.name }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="display_name">Nom affiché</Label>
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
                        >Votre bio
                        <span class="text-muted-foreground"
                            >(facultative)</span
                        ></Label
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
                    class="min-h-36 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <InputError :message="errors.bio" />
            </div>
        </section>

        <section
            v-show="currentStep === 3"
            class="space-y-7"
            aria-labelledby="profile-step-3-title"
        >
            <div>
                <h2
                    id="profile-step-3-title"
                    data-test="profile-step-title"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                    tabindex="-1"
                >
                    Vos affinités
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Ce que vous aimez et votre rythme de visite.
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

            <div class="grid gap-3">
                <Label for="visit_frequency">Fréquence de visite</Label>
                <select
                    id="visit_frequency"
                    v-model="visitFrequency"
                    name="visit_frequency"
                    required
                    class="min-h-14 rounded-2xl border border-input bg-background px-4 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option disabled value="">Choisir une fréquence</option>
                    <option
                        v-for="option in visitFrequencies"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.visit_frequency" />
            </div>
        </section>

        <section
            v-show="currentStep === 4"
            class="space-y-6"
            aria-labelledby="profile-step-4-title"
        >
            <div>
                <h2
                    id="profile-step-4-title"
                    data-test="profile-step-title"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                    tabindex="-1"
                >
                    Votre aperçu
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Voici comment les autres membres vous découvriront.
                </p>
            </div>

            <article
                class="overflow-hidden rounded-[2rem] border bg-card shadow-lg shadow-primary/10"
            >
                <div
                    v-if="selectedAvatar"
                    class="relative flex min-h-64 items-end justify-center overflow-hidden px-8 pt-6"
                    :style="{
                        backgroundImage: `linear-gradient(135deg, ${selectedAvatar.primary_color}, ${selectedAvatar.secondary_color})`,
                    }"
                >
                    <div
                        class="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(255,255,255,.45),transparent_36%)]"
                    />
                    <img
                        :src="selectedAvatar.image_url"
                        :alt="`Avatar ${selectedAvatar.name}`"
                        class="relative z-10 max-h-60 w-full object-contain drop-shadow-2xl"
                    />
                </div>
                <div
                    class="relative z-20 -mt-5 space-y-4 rounded-t-[2rem] bg-card p-5"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-3xl font-semibold tracking-tight">
                            {{ displayName || 'Votre nom' }}
                        </h3>
                        <Badge variant="secondary">{{ frequencyLabel }}</Badge>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ bio || 'Votre bio apparaîtra ici.' }}
                    </p>
                    <div
                        v-if="selectedInterests.length"
                        class="flex flex-wrap gap-2"
                    >
                        <Badge
                            v-for="interest in selectedInterests"
                            :key="interest.id"
                            variant="secondary"
                        >
                            {{ interest.name }}
                        </Badge>
                    </div>
                </div>
            </article>

            <div class="grid gap-3">
                <Label for="visibility">Visible dans les suggestions</Label>
                <div class="relative">
                    <Eye
                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-primary"
                        aria-hidden="true"
                    />
                    <select
                        id="visibility"
                        v-model="visibility"
                        name="visibility"
                        required
                        class="min-h-14 w-full appearance-none rounded-2xl border border-input bg-background pr-10 pl-12 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in visibilities"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Check
                        class="pointer-events-none absolute top-1/2 right-4 size-5 -translate-y-1/2 text-primary"
                        aria-hidden="true"
                    />
                </div>
                <InputError :message="errors.visibility" />
            </div>

            <p
                class="flex gap-3 rounded-2xl bg-primary/5 p-4 text-sm text-muted-foreground"
            >
                <Sparkles
                    class="size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                Vous pourrez modifier ces informations à tout moment.
            </p>
        </section>

        <footer
            class="sticky bottom-0 z-30 -mx-1 flex items-center gap-3 border-t bg-background/95 px-1 pt-4 pb-[max(0.25rem,env(safe-area-inset-bottom))] backdrop-blur"
        >
            <Button
                v-if="currentStep > 1"
                type="button"
                variant="ghost"
                class="min-h-12 px-4"
                @click="previous"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Retour
            </Button>
            <span v-else class="flex-1" />

            <Button
                v-if="currentStep < 4"
                type="button"
                :disabled="!canContinue"
                class="ml-auto min-h-12 flex-1 rounded-full bg-gradient-to-r from-primary to-pink-500 sm:max-w-64"
                @click="next"
            >
                Continuer
                <ArrowRight class="size-4" aria-hidden="true" />
            </Button>
            <Button
                v-else
                type="submit"
                :disabled="processing || avatars.length === 0"
                class="ml-auto min-h-12 flex-1 rounded-full bg-gradient-to-r from-primary to-pink-500 sm:max-w-72"
            >
                <Spinner v-if="processing" />
                <Check v-else class="size-4" aria-hidden="true" />
                {{ submitLabel }}
            </Button>
        </footer>
    </Form>
</template>
