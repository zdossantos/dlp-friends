<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
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
    <h1 class="sr-only">Tutoriel</h1>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Tutoriel"
            description="Revoyez à tout moment le parcours de démonstration de DLP Friends."
        />
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
                <Button type="button" :disabled="restarting" @click="relaunch">
                    Relancer le tutoriel
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
