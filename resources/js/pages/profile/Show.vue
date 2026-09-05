<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    LayoutDashboard,
    LogOut,
    Pencil,
    Settings,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import ProfilePresentation from '@/components/profile/ProfilePresentation.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard, logout } from '@/routes';
import { edit as editAccount } from '@/routes/account';
import { edit as editProfile } from '@/routes/member-profile';
import type { Profile, VisitFrequency } from '@/types';

const props = defineProps<{ profile: Profile; age: number }>();
const page = usePage();
const { t } = useTranslations();

const isAdmin = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'admin'),
);

const frequencyLabels: Record<VisitFrequency, string> = {
    rarely: t('profile.details.frequency_rarely'),
    sometimes: t('profile.details.frequency_sometimes'),
    often: t('profile.details.frequency_often'),
    very_often: t('profile.details.frequency_very_often'),
};

const visitFrequency = computed(() =>
    props.profile.visit_frequency
        ? frequencyLabels[props.profile.visit_frequency]
        : t('profile.details.frequency_unknown'),
);

function handleLogout(): void {
    router.clearHistory();
    router.flushAll();
}
</script>

<template>
    <Head :title="profile.display_name" />
    <main
        data-test="profile-page"
        class="mx-auto flex h-full min-h-0 w-full max-w-lg overflow-visible px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-4 sm:px-6 sm:pt-6"
    >
        <section
            v-if="profile.avatar"
            data-test="profile-card"
            class="h-full max-h-full w-full"
        >
            <ProfilePresentation
                :avatar="profile.avatar"
                :display-name="profile.display_name"
                :age-label="t('profile.details.age', { age })"
                :bio="profile.bio || t('profile.details.empty_bio')"
                :visit-frequency="visitFrequency"
                :interests="profile.interests ?? []"
                :about-label="t('profile.details.about')"
                :interests-label="t('profile.details.interests')"
                :visit-frequency-label="t('profile.details.visit_frequency')"
                :is-admin="isAdmin"
                class="h-full"
            >
                <template #hero-actions>
                    <div
                        class="flex gap-2"
                        :aria-label="t('profile.actions.label')"
                    >
                        <Button
                            as-child
                            variant="secondary"
                            size="icon"
                            class="size-10 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                        >
                            <Link
                                :href="editAccount()"
                                :aria-label="t('profile.actions.settings')"
                            >
                                <Settings class="size-5" aria-hidden="true" />
                            </Link>
                        </Button>
                        <Button
                            v-if="isAdmin"
                            as-child
                            variant="secondary"
                            size="icon"
                            class="size-10 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                        >
                            <Link
                                :href="dashboard()"
                                :aria-label="
                                    t('profile.actions.administration')
                                "
                            >
                                <LayoutDashboard
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="secondary"
                            size="icon"
                            class="size-10 rounded-full border border-white/50 bg-background/90 shadow-lg backdrop-blur"
                        >
                            <Link
                                :href="logout()"
                                as="button"
                                :aria-label="t('profile.actions.logout')"
                                @click="handleLogout"
                            >
                                <LogOut class="size-5" aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                </template>
                <template #badges>
                    <span
                        data-test="profile-visibility-badge"
                        class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-muted px-3 py-1.5 text-sm font-medium"
                    >
                        <Eye
                            v-if="profile.visibility === 'visible'"
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <EyeOff
                            v-else
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        {{
                            profile.visibility === 'visible'
                                ? t('profile.details.visible')
                                : t('profile.details.hidden')
                        }}
                    </span>
                    <span
                        data-test="profile-frequency-badge"
                        class="inline-flex items-center gap-2 rounded-full border border-secondary-foreground/15 bg-secondary px-3 py-1.5 text-sm font-medium text-secondary-foreground"
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                        {{ visitFrequency }}
                    </span>
                </template>
                <template #summary-actions>
                    <Button
                        as-child
                        variant="outline"
                        class="min-h-10 rounded-full"
                    >
                        <Link :href="editProfile()">
                            <Pencil class="size-4" aria-hidden="true" />
                            {{ t('profile.actions.edit') }}
                        </Link>
                    </Button>
                </template>
            </ProfilePresentation>
        </section>
    </main>
</template>
