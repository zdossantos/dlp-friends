<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { show } from '@/routes/onboarding';
import { restart } from '@/routes/onboarding-settings';

type Status = 'not_started' | 'in_progress' | 'completed' | 'skipped';

const props = defineProps<{
    onboarding: {
        status: Status;
        step: string | null;
        updatedAt: string | null;
    };
}>();

const { formatDate } = useTranslations();
const restarting = ref(false);
const statusLabels: Record<Status, string> = {
    not_started: 'Non commencé',
    in_progress: 'En cours',
    completed: 'Terminé',
    skipped: 'Ignoré',
};
const statusLabel = computed(() => statusLabels[props.onboarding.status]);

function relaunch(): void {
    restarting.value = true;
    router.post(
        restart().url,
        {},
        {
            onFinish: () => {
                restarting.value = false;
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tutoriel', href: { url: '/settings/onboarding' } },
        ],
    },
});
</script>

<template>
    <Head title="Tutoriel" />
    <div class="space-y-6">
        <header>
            <h1 class="mb-0.5 text-base font-medium">Tutoriel</h1>
            <p class="text-sm text-muted-foreground">
                Revoyez à tout moment le parcours de démonstration de DLP
                Friends.
            </p>
        </header>
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center justify-between gap-3">
                    État du tutoriel
                    <Badge variant="secondary">{{ statusLabel }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p
                    v-if="onboarding.updatedAt"
                    class="text-sm text-muted-foreground"
                >
                    Dernière activité :
                    {{
                        formatDate(onboarding.updatedAt, {
                            dateStyle: 'long',
                            timeStyle: 'short',
                        })
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Cette démonstration utilise uniquement des profils et
                    messages fictifs.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="onboarding.status === 'in_progress'" as-child>
                        <Link :href="show()">Reprendre le tutoriel</Link>
                    </Button>
                    <Button
                        type="button"
                        :variant="
                            onboarding.status === 'in_progress'
                                ? 'outline'
                                : 'default'
                        "
                        :disabled="restarting"
                        @click="relaunch"
                    >
                        {{
                            onboarding.status === 'in_progress'
                                ? 'Recommencer depuis le début'
                                : 'Relancer le tutoriel'
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
