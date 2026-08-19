<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/account';
import { send } from '@/routes/verification';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Compte', href: edit() }] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Réglages du compte" />
    <h1 class="sr-only">Réglages du compte</h1>
    <div class="flex flex-col space-y-10">
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Compte"
                description="Modifiez votre adresse e-mail de connexion."
            />
            <Form
                v-bind="AccountController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="email"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div
                    v-if="page.props.mustVerifyEmail && !user.email_verified_at"
                    class="text-sm text-muted-foreground"
                >
                    Votre adresse e-mail n’est pas vérifiée.
                    <Link
                        :href="send()"
                        as="button"
                        class="font-medium text-primary underline underline-offset-4"
                    >
                        Renvoyer le lien de vérification
                    </Link>
                </div>
                <Button :disabled="processing" data-test="update-account-button"
                    >Enregistrer</Button
                >
            </Form>
        </div>
        <DeleteUser />
    </div>
</template>
