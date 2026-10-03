<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BlockMemberDialog from '@/components/members/BlockMemberDialog.vue';
import LikeMemberButton from '@/components/members/LikeMemberButton.vue';
import ProfilePresentation from '@/components/profile/ProfilePresentation.vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerTitle,
} from '@/components/ui/drawer';
import { useTranslations } from '@/composables/useTranslations';
import { index as explore } from '@/routes/discovery';
import {
    index as passedProfiles,
    like as likePassed,
    show as showPassed,
} from '@/routes/discovery/passed';
import type { PublicMember } from '@/types';

const props = defineProps<{
    selectedProfile: {
        member: PublicMember;
        canLike: boolean;
        canBlock: boolean;
    } | null;
    profiles: {
        data: Pick<
            PublicMember,
            'id' | 'display_name' | 'age' | 'avatar' | 'is_admin'
        >[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const { t } = useTranslations();
const page = usePage();
const loading = ref(false);
const error = ref<string | null>(null);
const retryUrl = ref<string | null>(null);
const drawerOpen = ref(props.selectedProfile !== null);
const profileLoading = ref(false);
const profileError = ref<string | null>(null);
const retryProfileId = ref<number | null>(null);
const profileList = ref<HTMLElement | null>(null);
let profileTrigger: HTMLElement | null = null;
const historyHref = computed(
    () => passedProfiles({ query: { page: props.profiles.current_page } }).url,
);
const visitFrequency = computed(() => {
    const frequency = props.selectedProfile?.member.visit_frequency;

    return t(
        frequency
            ? `profile.details.frequency_${frequency}`
            : 'profile.details.frequency_unknown',
    );
});
watch(
    () => props.selectedProfile,
    (profile) => {
        if (profile === null) {
            const message =
                page.props.errors.target ?? page.props.errors.decision;
            profileError.value = message ? String(message) : null;
            drawerOpen.value = Boolean(message);
        }
    },
);

function openProfile(memberId: number, event?: MouseEvent): void {
    if (event) {
        profileTrigger = event.currentTarget as HTMLElement;
    }

    retryProfileId.value = memberId;
    drawerOpen.value = true;
    router.get(
        showPassed(memberId).url,
        { page: props.profiles.current_page },
        {
            only: ['selectedProfile'],
            preserveState: true,
            preserveScroll: true,
            preserveUrl: true,
            onStart: () => {
                profileLoading.value = true;
                profileError.value = null;
            },
            onFinish: () => (profileLoading.value = false),
            onHttpException: (response) => {
                profileError.value = t(
                    response.status === 404
                        ? 'discovery.errors.target_unavailable'
                        : 'discovery.passed.server_error',
                );

                return false;
            },
            onNetworkError: () => {
                profileError.value = t('discovery.passed.network_error');

                return false;
            },
        },
    );
}

function restoreProfileFocus(event: Event): void {
    event.preventDefault();
    const target = profileTrigger?.isConnected
        ? profileTrigger
        : profileList.value;
    target?.focus({ preventScroll: true });
}
function navigate(url: string | null): void {
    if (!url || loading.value) {
        return;
    }

    retryUrl.value = url;

    router.get(
        url,
        {},
        {
            preserveState: true,
            onStart: () => {
                loading.value = true;
                error.value = null;
            },
            onFinish: () => {
                loading.value = false;
            },
            onError: () => {
                error.value = t('discovery.page.generic_error');
            },
            onHttpException: () => {
                error.value = t('discovery.passed.server_error');

                return false;
            },
            onNetworkError: () => {
                error.value = t('discovery.passed.network_error');

                return false;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('discovery.passed.title')" />
    <main
        class="mx-auto flex h-full min-h-0 w-full max-w-2xl flex-col gap-4 px-4 pt-4 pb-4 sm:px-6 sm:pt-6"
        :aria-busy="loading"
    >
        <header class="shrink-0 space-y-2">
            <Button as-child variant="outline"
                ><Link :href="explore()">{{
                    t('discovery.passed.back')
                }}</Link></Button
            >
            <h1 class="text-2xl font-semibold">
                {{ t('discovery.passed.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('discovery.passed.description') }}
            </p>
        </header>
        <p v-if="loading" role="status">{{ t('discovery.passed.loading') }}</p>
        <Alert v-if="error" variant="destructive" aria-live="assertive"
            ><AlertDescription class="space-y-2">
                <p>{{ error }}</p>
                <Button
                    variant="outline"
                    :disabled="loading"
                    @click="navigate(retryUrl)"
                    >{{ t('discovery.page.retry') }}</Button
                >
            </AlertDescription></Alert
        >
        <section
            v-if="props.profiles.data.length === 0"
            class="rounded-3xl border bg-card p-6 text-center"
        >
            <h2 class="font-semibold">{{ t('discovery.passed.empty') }}</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('discovery.passed.empty_description') }}
            </p>
        </section>
        <ul
            v-else
            ref="profileList"
            data-test="passed-profile-list"
            scroll-region
            tabindex="-1"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto p-1"
        >
            <li v-for="member in profiles.data" :key="member.id">
                <button
                    type="button"
                    :data-profile-id="member.id"
                    data-test="passed-profile"
                    class="flex min-h-11 w-full items-center gap-3 rounded-xl border bg-card p-4 text-left focus-visible:ring-2 focus-visible:ring-ring"
                    :class="member.is_admin ? 'border-amber-400' : ''"
                    @click="openProfile(member.id, $event)"
                >
                    <UserAvatar
                        :avatar="member.avatar"
                        :display-name="member.display_name"
                        class="size-14 shrink-0"
                    />
                    <div class="min-w-0">
                        <p class="font-semibold break-words">
                            {{ member.display_name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ t('discovery.card.age', { age: member.age }) }}
                        </p>
                        <Badge v-if="member.is_admin" variant="secondary">{{
                            t('profile.details.administrator')
                        }}</Badge>
                    </div>
                </button>
            </li>
        </ul>
        <nav
            v-if="profiles.last_page > 1"
            :aria-label="t('discovery.passed.pagination')"
            class="flex shrink-0 flex-wrap items-center justify-center gap-2"
        >
            <Button
                variant="outline"
                :disabled="!profiles.prev_page_url || loading"
                @click="navigate(profiles.prev_page_url)"
                >{{ t('discovery.passed.previous') }}</Button
            >
            <span class="text-sm">{{
                t('discovery.passed.page', {
                    page: profiles.current_page,
                    total: profiles.last_page,
                })
            }}</span>
            <Button
                variant="outline"
                :disabled="!profiles.next_page_url || loading"
                @click="navigate(profiles.next_page_url)"
                >{{ t('discovery.passed.next') }}</Button
            >
        </nav>
        <Drawer v-model:open="drawerOpen">
            <DrawerContent
                data-test="passed-profile-drawer"
                class="mx-auto h-[85svh] max-h-[85svh] w-full max-w-lg overflow-hidden px-4 pb-[max(1rem,env(safe-area-inset-bottom))] [&>[data-slot=drawer-handle]]:hidden"
                :class="{ 'pt-16': profileLoading || profileError }"
                @close-auto-focus="restoreProfileFocus"
            >
                <DrawerTitle class="sr-only">{{
                    profileLoading || profileError
                        ? t('discovery.passed.title')
                        : (selectedProfile?.member.display_name ??
                          t('discovery.passed.title'))
                }}</DrawerTitle>
                <DrawerDescription class="sr-only">{{
                    t('discovery.passed.description')
                }}</DrawerDescription>
                <DrawerClose as-child>
                    <Button
                        data-test="passed-profile-close"
                        variant="outline"
                        size="icon"
                        class="absolute top-3 left-7 z-40 size-11 rounded-full bg-background/90"
                        :aria-label="t('common.actions.close')"
                    >
                        <X aria-hidden="true" class="size-5" />
                    </Button>
                </DrawerClose>
                <p v-if="profileLoading" role="status">
                    {{ t('discovery.passed.loading') }}
                </p>
                <Alert
                    v-else-if="profileError"
                    variant="destructive"
                    aria-live="assertive"
                >
                    <AlertDescription class="space-y-2">
                        <p>{{ profileError }}</p>
                        <Button
                            variant="outline"
                            @click="
                                retryProfileId && openProfile(retryProfileId)
                            "
                            >{{ t('discovery.page.retry') }}</Button
                        >
                    </AlertDescription>
                </Alert>
                <ProfilePresentation
                    v-else-if="selectedProfile"
                    embedded
                    :avatar="selectedProfile.member.avatar"
                    :display-name="selectedProfile.member.display_name"
                    :age-label="
                        t('profile.details.age', {
                            age: selectedProfile.member.age,
                        })
                    "
                    :bio="
                        selectedProfile.member.bio ??
                        t('profile.details.empty_bio')
                    "
                    :visit-frequency="visitFrequency"
                    :interests="selectedProfile.member.interests"
                    :about-label="t('profile.details.about')"
                    :interests-label="t('profile.details.interests')"
                    :visit-frequency-label="
                        t('profile.details.visit_frequency')
                    "
                    :is-admin="selectedProfile.member.is_admin"
                    class="min-h-0 flex-1 rounded-[2rem]"
                >
                    <template #summary-actions>
                        <LikeMemberButton
                            v-if="selectedProfile.canLike"
                            :member-id="selectedProfile.member.id"
                            :action-href="
                                likePassed(selectedProfile.member.id, {
                                    query: { page: profiles.current_page },
                                }).url
                            "
                            :label="t('discovery.actions.discover')"
                        />
                        <BlockMemberDialog
                            v-if="selectedProfile.canBlock"
                            :member-id="selectedProfile.member.id"
                            :return-href="historyHref"
                        />
                    </template>
                </ProfilePresentation>
            </DrawerContent>
        </Drawer>
    </main>
</template>
