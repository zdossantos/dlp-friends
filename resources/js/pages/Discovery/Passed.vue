<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index as explore } from '@/routes/discovery';
import { show as showPassed } from '@/routes/discovery/passed';
import type { PublicMember } from '@/types';

const props = defineProps<{
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
const loading = ref(false);
const error = ref<string | null>(null);
const retryUrl = ref<string | null>(null);
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
        <ul v-else class="min-h-0 flex-1 space-y-3 overflow-y-auto p-1">
            <li v-for="member in profiles.data" :key="member.id">
                <Link
                    :href="showPassed(member.id)"
                    data-test="passed-profile"
                    class="flex min-h-11 items-center gap-3 rounded-xl border bg-card p-4 focus-visible:ring-2 focus-visible:ring-ring"
                    :class="member.is_admin ? 'border-amber-400' : ''"
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
                </Link>
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
    </main>
</template>
