<script setup lang="ts">
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DiscoveryProfile, SwipeDecision, VisitFrequency } from '@/types';

const SWIPE_THRESHOLD_PX = 72;

const props = defineProps<{
    profile: DiscoveryProfile;
    locked: boolean;
}>();

const emit = defineEmits<{ like: []; pass: [] }>();

const visitFrequencyLabels: Record<VisitFrequency, string> = {
    rarely: 'Rarement',
    sometimes: 'De temps en temps',
    often: 'Souvent',
    very_often: 'Très souvent',
};

const pointerStartX = ref<number | null>(null);

const initials = computed(() => {
    return props.profile.displayName
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toLocaleUpperCase('fr-FR');
});

const visitFrequencyLabel = computed(() => {
    return props.profile.visitFrequency
        ? visitFrequencyLabels[props.profile.visitFrequency]
        : 'Fréquence non renseignée';
});

const formattedScore = computed(() => {
    return new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 2,
        minimumFractionDigits: props.profile.score % 1 === 0 ? 0 : 2,
    }).format(props.profile.score);
});

function decide(decision: SwipeDecision) {
    if (props.locked) {
        return;
    }

    if (decision === 'like') {
        emit('like');

        return;
    }

    emit('pass');
}

function rememberPointerStart(event: PointerEvent) {
    pointerStartX.value = event.clientX;
}

function forgetPointerStart() {
    pointerStartX.value = null;
}

function handlePointerEnd(event: PointerEvent) {
    if (pointerStartX.value === null) {
        return;
    }

    const deltaX = event.clientX - pointerStartX.value;

    forgetPointerStart();

    if (deltaX <= -SWIPE_THRESHOLD_PX) {
        decide('pass');
    }

    if (deltaX >= SWIPE_THRESHOLD_PX) {
        decide('like');
    }
}
</script>

<template>
    <Card
        class="w-full max-w-md touch-pan-y gap-4 p-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        tabindex="0"
        :aria-label="`Profil de découverte de ${profile.displayName}`"
        @keydown.left.prevent.stop="decide('pass')"
        @keydown.right.prevent.stop="decide('like')"
        @pointerdown="rememberPointerStart"
        @pointerup="handlePointerEnd"
        @pointercancel="forgetPointerStart"
    >
        <CardHeader class="gap-4 px-0">
            <div class="flex items-start gap-4">
                <Avatar class="size-16 border">
                    <AvatarFallback class="text-lg font-semibold">
                        {{ initials }}
                    </AvatarFallback>
                </Avatar>

                <div class="min-w-0 flex-1">
                    <CardTitle class="text-2xl leading-tight">
                        {{ profile.displayName }}
                    </CardTitle>
                    <CardDescription class="mt-1 text-base">
                        {{ profile.age }} ans
                    </CardDescription>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5 px-0">
            <p class="text-sm leading-6 text-muted-foreground">
                {{ profile.bio ?? 'Bio non renseignée.' }}
            </p>

            <div class="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm">
                <p class="font-medium">
                    Score {{ formattedScore }} ·
                    {{ profile.commonPassionCount }} passions communes
                </p>
                <p class="text-muted-foreground">
                    Fréquence de visite : {{ visitFrequencyLabel }}
                </p>
                <p v-if="profile.frequencyBonus" class="text-muted-foreground">
                    Bonus de fréquence inclus dans ce score.
                </p>
            </div>

            <div class="flex flex-wrap gap-2" aria-label="Passions communes">
                <Badge
                    v-for="passion in profile.commonPassions"
                    :key="passion"
                    variant="secondary"
                >
                    {{ passion }}
                </Badge>
            </div>
        </CardContent>

        <CardFooter class="grid gap-3 px-0">
            <p class="text-center text-sm text-muted-foreground">
                Utilisez les boutons ou les flèches gauche et droite.
            </p>

            <div class="grid grid-cols-2 gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    :disabled="locked"
                    aria-label="Passer ce profil"
                    @click.stop="decide('pass')"
                >
                    Passer
                </Button>
                <Button
                    type="button"
                    class="w-full"
                    :disabled="locked"
                    aria-label="Aimer ce profil"
                    @click.stop="decide('like')"
                >
                    J'aime
                </Button>
            </div>
        </CardFooter>
    </Card>
</template>
