<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
const SWIPE_EXIT_DURATION_MS = 280;

const props = withDefaults(
    defineProps<{
        profile: DiscoveryProfile;
        locked: boolean;
        preview?: boolean;
    }>(),
    { preview: false },
);

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
const dragOffset = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const exitDirection = ref<-1 | 0 | 1>(0);
let exitTimer: number | undefined;

const cardStyle = computed(() => {
    const rotation = Math.max(-14, Math.min(14, dragOffset.value.x / 20));
    const transform = exitDirection.value
        ? `translate3d(${exitDirection.value * 120}vw, ${dragOffset.value.y}px, 0) rotate(${exitDirection.value * 18}deg)`
        : `translate3d(${dragOffset.value.x}px, ${dragOffset.value.y}px, 0) rotate(${rotation}deg)`;

    return {
        opacity: exitDirection.value ? '0' : '1',
        transform,
        transitionDuration: isDragging.value ? '0ms' : '280ms',
    };
});

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

function decide(decision: SwipeDecision) {
    if (props.locked || props.preview || exitDirection.value !== 0) {
        return;
    }

    if (decision === 'like') {
        emit('like');

        return;
    }

    emit('pass');
}

function animateDecision(decision: SwipeDecision) {
    if (props.locked || props.preview || exitDirection.value !== 0) {
        return;
    }

    isDragging.value = false;
    exitDirection.value = decision === 'like' ? 1 : -1;

    const prefersReducedMotion =
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
        false;
    exitTimer = window.setTimeout(
        () => {
            if (decision === 'like') {
                emit('like');
            } else {
                emit('pass');
            }
        },
        prefersReducedMotion ? 0 : SWIPE_EXIT_DURATION_MS,
    );
}

function rememberPointerStart(event: PointerEvent) {
    if (props.locked || props.preview || exitDirection.value !== 0) {
        return;
    }

    const target = event.currentTarget as HTMLElement | null;

    pointerStart.value = {
        pointerId: event.pointerId,
        x: event.clientX,
        y: event.clientY,
    };
    dragOffset.value = { x: 0, y: 0 };
    isDragging.value = true;
    target?.setPointerCapture?.(event.pointerId);
}

function handlePointerMove(event: PointerEvent) {
    const start = pointerStart.value;

    if (start === null || start.pointerId !== event.pointerId) {
        return;
    }

    const deltaX = event.clientX - start.x;
    const deltaY = event.clientY - start.y;

    dragOffset.value = { x: deltaX, y: deltaY * 0.15 };

    if (Math.abs(deltaX) > Math.abs(deltaY)) {
        event.preventDefault();
    }
}

function forgetPointerStart(event?: PointerEvent) {
    const target = event?.currentTarget as HTMLElement | null;

    if (event && target?.hasPointerCapture?.(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    pointerStart.value = null;
    isDragging.value = false;
}

function resetCard(event?: PointerEvent) {
    forgetPointerStart(event);
    dragOffset.value = { x: 0, y: 0 };
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
        dragOffset.value = { x: 0, y: 0 };

        return;
    }

    if (deltaX <= -SWIPE_THRESHOLD_PX) {
        animateDecision('pass');

        return;
    }

    if (deltaX >= SWIPE_THRESHOLD_PX) {
        animateDecision('like');

        return;
    }

    dragOffset.value = { x: 0, y: 0 };
}

onBeforeUnmount(() => window.clearTimeout(exitTimer));

watch(
    () => props.locked,
    (locked, wasLocked) => {
        if (!locked && wasLocked && exitDirection.value !== 0) {
            exitDirection.value = 0;
            dragOffset.value = { x: 0, y: 0 };
        }
    },
);
</script>

<template>
    <Card
        class="w-full max-w-md touch-pan-y gap-0 overflow-hidden rounded-[1.75rem] p-0 transition-[transform,opacity] ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:duration-0"
        :style="cardStyle"
        :tabindex="preview ? -1 : 0"
        :aria-label="`Profil de découverte de ${profile.displayName}`"
        aria-describedby="swipe-instructions"
        @keydown.left.self.prevent.stop="decide('pass')"
        @keydown.right.self.prevent.stop="decide('like')"
        @pointerdown="rememberPointerStart"
        @pointermove="handlePointerMove"
        @pointerup="handlePointerEnd"
        @pointercancel="resetCard"
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
                    {{ profile.commonInterestCount }} intérêts communs
                </p>
                <p class="text-muted-foreground">
                    Fréquence de visite : {{ visitFrequencyLabel }}
                </p>
                <p v-if="profile.frequencyBonus" class="text-muted-foreground">
                    Même fréquence de visite
                </p>
            </div>

            <div class="flex flex-wrap gap-2" aria-label="Intérêts communs">
                <Badge
                    v-for="interest in profile.commonInterests"
                    :key="interest"
                    variant="secondary"
                >
                    {{ interest }}
                </Badge>
            </div>

            <p id="swipe-instructions" class="sr-only">
                Balayez vers la gauche pour passer ce profil ou vers la droite
                pour l’aimer. Au clavier, utilisez les flèches gauche et droite.
            </p>
            <button
                class="sr-only"
                type="button"
                :disabled="locked || preview"
                aria-label="Passer ce profil"
                @click="decide('pass')"
            >
                Passer ce profil
            </button>
            <button
                class="sr-only"
                type="button"
                :disabled="locked || preview"
                aria-label="Aimer ce profil"
                @click="decide('like')"
            >
                Aimer ce profil
            </button>
        </CardContent>
    </Card>
</template>
