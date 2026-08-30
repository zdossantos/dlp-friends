<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { localizeMailError } from '@/lib/mailError';
import { login } from '@/routes';
import { email } from '@/routes/password';

const { t } = useTranslations();

defineOptions({
    layout: {
        title: 'Mot de passe oublié',
        description: 'Recevez un lien pour choisir un nouveau mot de passe.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div
        v-if="status"
        role="status"
        class="mb-4 rounded-xl bg-secondary p-3 text-center text-sm font-medium text-secondary-foreground"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Adresse e-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="vous@exemple.fr"
                />
                <InputError :message="localizeMailError(errors.email, t)" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Envoyer le lien de réinitialisation
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Revenir à la</span>
            <TextLink :href="login()">connexion</TextLink>
        </div>
    </div>
</template>
