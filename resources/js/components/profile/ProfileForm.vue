<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import InterestTagSelector from '@/components/profile/InterestTagSelector.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { AvatarOption, InterestOption, Profile } from '@/types';

type Option = { value: string; label: string };

defineProps<{
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
</script>

<template>
    <Form
        :action="action"
        :method="method"
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
        <fieldset class="grid gap-3">
            <legend class="text-sm font-medium">Choisissez votre avatar</legend>
            <p class="text-xs text-muted-foreground">
                Ce choix est obligatoire pour compléter votre profil.
            </p>
            <div
                v-if="avatars.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <label
                    v-for="avatar in avatars"
                    :key="avatar.id"
                    class="group cursor-pointer rounded-3xl border p-2 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                >
                    <input
                        class="sr-only"
                        type="radio"
                        name="avatar_id"
                        :value="avatar.id"
                        :checked="profile?.avatar_id === avatar.id"
                        required
                    />
                    <AvatarPortrait
                        :avatar="avatar"
                        :data-test="`avatar-option-${avatar.id}`"
                    />
                    <span class="mt-2 block text-center text-sm font-medium">
                        {{ avatar.name }}
                    </span>
                </label>
            </div>
            <p
                v-else
                class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            >
                Aucun avatar n’est disponible pour le moment.
            </p>
            <InputError :message="errors.avatar_id" />
        </fieldset>

        <div class="grid gap-2">
            <Label for="display_name">Nom affiché</Label>
            <Input
                id="display_name"
                name="display_name"
                autocomplete="nickname"
                required
                maxlength="80"
                :default-value="profile?.display_name ?? ''"
            />
            <InputError :message="errors.display_name" />
        </div>

        <div class="grid gap-2">
            <Label for="bio"
                >Bio
                <span class="text-muted-foreground">(facultative)</span></Label
            >
            <textarea
                id="bio"
                name="bio"
                maxlength="500"
                :value="profile?.bio ?? ''"
                class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            />
            <p class="text-xs text-muted-foreground">500 caractères maximum.</p>
            <InputError :message="errors.bio" />
        </div>

        <div class="grid gap-2">
            <Label for="visit_frequency">Fréquence de visite</Label>
            <select
                id="visit_frequency"
                name="visit_frequency"
                required
                :value="profile?.visit_frequency ?? ''"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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

        <div class="grid gap-2">
            <Label for="visibility">Visible dans les suggestions</Label>
            <select
                id="visibility"
                name="visibility"
                required
                :value="profile?.visibility ?? 'visible'"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option
                    v-for="option in visibilities"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                Vous pourrez masquer temporairement votre profil plus tard.
            </p>
            <InputError :message="errors.visibility" />
        </div>

        <div class="grid gap-2">
            <InterestTagSelector
                :interests="interests"
                :selected-ids="selectedInterestIds"
                :limit="interestLimit"
            />
            <InputError :message="errors.interest_ids" />
        </div>

        <Button
            type="submit"
            :disabled="processing || avatars.length === 0"
            class="w-full sm:w-auto"
        >
            <Spinner v-if="processing" />
            {{ submitLabel }}
        </Button>
    </Form>
</template>
