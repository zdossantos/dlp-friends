<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Créer un compte',
        description:
            'Vous créerez ensuite votre profil, puis un tutoriel vous expliquera comment rencontrer d’autres membres.',
    },
});
</script>

<template>
    <Head title="Créer un compte" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="1"
                    autocomplete="email"
                    name="email"
                    placeholder="vous@exemple.fr"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="birth_date">Date de naissance</Label>
                <Input
                    id="birth_date"
                    type="date"
                    required
                    :tabindex="2"
                    autocomplete="bday"
                    name="birth_date"
                />
                <InputError :message="errors.birth_date" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Mot de passe"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Confirmer le mot de passe</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirmer le mot de passe"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Créer mon compte
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Vous avez déjà un compte ?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Se connecter</TextLink
            >
        </div>
    </Form>
</template>
