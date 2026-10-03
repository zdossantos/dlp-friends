<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';

type Member = {
    id: number;
    display_name: string;
    email: string;
    status: 'not_started' | 'in_progress' | 'completed';
    step: string | null;
    updated_at: string;
};

defineProps<{
    member: Member;
    statusLabel: string;
    stepLabel: string;
}>();
const { formatDate, t } = useTranslations();
</script>

<template>
    <Card class="min-w-0" data-test="onboarding-progress-card">
        <CardHeader class="min-w-0 gap-2">
            <CardTitle class="text-lg break-words">{{
                member.display_name
            }}</CardTitle>
            <p class="text-sm break-all text-muted-foreground">
                {{ member.email }}
            </p>
        </CardHeader>
        <CardContent class="grid gap-3 text-sm">
            <div>
                <p class="font-medium">
                    {{ t('administration.onboarding.status') }}
                </p>
                <Badge
                    :variant="
                        member.status === 'completed' ? 'default' : 'secondary'
                    "
                >
                    {{ statusLabel }}
                </Badge>
            </div>
            <div>
                <p class="font-medium">
                    {{ t('administration.onboarding.step') }}
                </p>
                <p class="break-words text-muted-foreground">{{ stepLabel }}</p>
            </div>
            <div>
                <p class="font-medium">
                    {{ t('administration.onboarding.updated_at') }}
                </p>
                <p class="text-muted-foreground">
                    {{
                        formatDate(member.updated_at, {
                            dateStyle: 'short',
                            timeStyle: 'short',
                        })
                    }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
