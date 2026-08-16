<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Profile } from '@/types';

type Option = { value: string; label: string };

defineProps<{
    profile: Profile | null;
    action: string;
    method: 'post' | 'patch';
    submitLabel: string;
    visitFrequencies: Option[];
    visibilities: Option[];
}>();
</script>

<template>
    <Form
        :action="action"
        :method="method"
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
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

        <Button type="submit" :disabled="processing" class="w-full sm:w-auto">
            <Spinner v-if="processing" />
            {{ submitLabel }}
        </Button>
    </Form>
</template>
