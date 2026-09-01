<script setup lang="ts">
import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/account';
import { send } from '@/routes/verification';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useTranslations();
setLayoutProps({
    breadcrumbs: [{ title: t('account.settings.account'), href: edit() }],
});
</script>

<template>
    <Head :title="t('account.settings.account_page_title')" />
    <h1 class="sr-only">{{ t('account.settings.account_page_title') }}</h1>
    <div class="flex flex-col space-y-10">
        <div class="space-y-6">
            <LocaleSwitcher />
            <Heading
                variant="small"
                :title="t('account.settings.account')"
                :description="t('account.settings.account_description')"
            />
            <Form
                v-bind="AccountController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="email">{{ t('account.fields.email') }}</Label>
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
                    {{ t('account.settings.email_unverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="font-medium text-primary underline underline-offset-4"
                    >
                        {{ t('account.settings.resend_verification') }}
                    </Link>
                </div>
                <Button
                    :disabled="processing"
                    :aria-busy="processing ? 'true' : undefined"
                    data-test="update-account-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('account.settings.save') }}
                </Button>
            </Form>
        </div>
        <DeleteUser />
    </div>
</template>
