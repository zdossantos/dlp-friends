<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Vérifiez votre adresse e-mail',
        description:
            'Cliquez sur le lien que nous venons de vous envoyer avant de créer votre profil.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Vérification de l’adresse e-mail" />

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="mb-4 rounded-xl bg-secondary p-3 text-center text-sm font-medium text-secondary-foreground"
    >
        Un nouveau lien de vérification vient d’être envoyé à votre adresse
        e-mail.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Renvoyer l’e-mail de vérification
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Se déconnecter
        </TextLink>
    </Form>
</template>
