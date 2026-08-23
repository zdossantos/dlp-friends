<script setup lang="ts">
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
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

const pointerStart = ref<{
    pointerId: number;
    x: number;
    y: number;
} | null>(null);

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
    const target = event.currentTarget as HTMLElement | null;

    pointerStart.value = {
        pointerId: event.pointerId,
        x: event.clientX,
        y: event.clientY,
    };
    target?.setPointerCapture?.(event.pointerId);
}

function forgetPointerStart(event?: PointerEvent) {
    const target = event?.currentTarget as HTMLElement | null;

    if (event && target?.hasPointerCapture?.(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    pointerStart.value = null;
}

function handlePointerEnd(event: PointerEvent) {
    const start = pointerStart.value;

    if (start === null || start.pointerId !== event.pointerId) {
        return;
    }

    const deltaX = event.clientX - start.x;
    const deltaY = event.clientY - start.y;

    forgetPointerStart(event);

    if (Math.abs(deltaX) <= Math.abs(deltaY)) {
        return;
    }

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
        class="w-full max-w-md touch-pan-y gap-0 overflow-hidden rounded-[1.75rem] p-0 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        tabindex="0"
        :aria-label="`Profil de découverte de ${profile.displayName}`"
        aria-describedby="swipe-instructions"
        @keydown.left.self.prevent.stop="decide('pass')"
        @keydown.right.self.prevent.stop="decide('like')"
        @pointerdown="rememberPointerStart"
        @pointerup="handlePointerEnd"
        @pointercancel="forgetPointerStart"
        @lostpointercapture="forgetPointerStart"
    >
        <CardHeader
            class="gap-4 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_50%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_48%)] px-6 py-8"
        >
            <div class="flex flex-col items-center gap-4 text-center">
                <Avatar
                    class="size-24 rounded-3xl border-4 border-card shadow-lg"
                >
                    <AvatarFallback
                        class="rounded-3xl bg-card/70 text-2xl font-semibold text-primary"
                    >
                        {{ initials }}
                    </AvatarFallback>
                </Avatar>

                <div class="min-w-0 flex-1">
                    <CardTitle class="text-3xl leading-tight tracking-tight">
                        {{ profile.displayName }}
                    </CardTitle>
                    <CardDescription class="mt-1 text-base">
                        {{ profile.age }} ans
                    </CardDescription>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-5 px-6 py-6">
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

            <p id="swipe-instructions" class="sr-only">
                Balayez vers la gauche pour passer ce profil ou vers la droite
                pour l’aimer. Au clavier, utilisez les flèches gauche et droite.
            </p>
            <button
                class="sr-only"
                type="button"
                :disabled="locked"
                aria-label="Passer ce profil"
                @click="decide('pass')"
            >
                Passer ce profil
            </button>
            <button
                class="sr-only"
                type="button"
                :disabled="locked"
                aria-label="Aimer ce profil"
                @click="decide('like')"
            >
                Aimer ce profil
            </button>
        </CardContent>
    </Card>
</template>
