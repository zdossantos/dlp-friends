<script setup lang="ts">
import { Sparkles, Users, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { DiscoveryProfile, SwipeDecision, VisitFrequency } from '@/types';

const SWIPE_THRESHOLD_PX = 72;
const SWIPE_EXIT_DURATION_MS = 280;

const props = withDefaults(
    defineProps<{
        profile: DiscoveryProfile;
        locked: boolean;
        preview?: boolean;
        compact?: boolean;
    }>(),
    { compact: false, preview: false },
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

const visitFrequencyLabel = computed(() => {
    return props.profile.visitFrequency
        ? visitFrequencyLabels[props.profile.visitFrequency]
        : 'Fréquence non renseignée';
});

const hasDenseInterestList = computed(
    () => !props.compact && props.profile.interests.length >= 4,
);

const avatarGradient = computed(() => ({
    backgroundImage: `linear-gradient(145deg, ${props.profile.avatar.primary_color}, ${props.profile.avatar.secondary_color})`,
}));

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
        class="max-h-full w-full max-w-md touch-pan-y gap-0 overflow-hidden rounded-[2rem] p-0 shadow-xl shadow-primary/10 transition-[transform,opacity] ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:duration-0"
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
        <div
            data-test="discovery-avatar-hero"
            :class="[
                'relative flex shrink-0 items-end justify-center overflow-hidden',
                compact
                    ? 'min-h-40 px-5 pt-3'
                    : hasDenseInterestList
                      ? 'h-[clamp(12rem,27svh,14rem)] px-6 pt-4 sm:h-72'
                      : 'h-[clamp(15.5rem,34svh,17.5rem)] px-6 pt-4 sm:h-80',
            ]"
            :style="avatarGradient"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_70%_18%,rgba(255,255,255,.5),transparent_34%),radial-gradient(circle_at_12%_72%,rgba(255,255,255,.2),transparent_30%)]"
            />
            <div
                class="absolute right-[8%] bottom-[14%] size-36 rounded-full bg-white/20 blur-3xl"
                aria-hidden="true"
            />
            <img
                :src="profile.avatar.image_url"
                :alt="`Avatar ${profile.avatar.name}`"
                draggable="false"
                :class="[
                    'pointer-events-none relative z-10 w-full object-contain drop-shadow-2xl select-none',
                    compact
                        ? 'max-h-40'
                        : hasDenseInterestList
                          ? 'max-h-[13.5rem] sm:max-h-[17rem]'
                          : 'max-h-[17rem] sm:max-h-[19rem]',
                ]"
            />
        </div>

        <div
            data-test="discovery-information-sheet"
            :class="[
                'relative z-20 rounded-t-[2rem] bg-card',
                compact
                    ? '-mt-5 space-y-2 px-4 pt-4 pb-3'
                    : '-mt-6 space-y-2.5 px-4 pt-4 pb-4',
            ]"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        :class="[
                            'font-semibold tracking-tight',
                            compact ? 'text-2xl' : 'text-2xl sm:text-3xl',
                        ]"
                    >
                        {{ profile.displayName }}
                    </h2>
                    <Badge class="rounded-full px-3 py-1" variant="secondary">
                        {{ profile.age }} ans
                    </Badge>
                </div>
                <p
                    :class="[
                        'text-sm text-muted-foreground',
                        compact
                            ? 'mt-1 line-clamp-2 leading-5'
                            : 'mt-1.5 max-h-10 overflow-hidden leading-5',
                    ]"
                >
                    {{ profile.bio ?? 'Bio non renseignée.' }}
                </p>
            </div>

            <div class="space-y-1.5">
                <p
                    class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    <Users class="size-3.5 text-primary" aria-hidden="true" />
                    {{ profile.commonInterestCount }}
                    {{
                        profile.commonInterestCount > 1
                            ? 'intérêts en commun'
                            : 'intérêt en commun'
                    }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <Badge
                        v-for="interest in profile.interests"
                        :key="interest.name"
                        data-test="discovery-interest"
                        :data-common="interest.isCommon"
                        :variant="interest.isCommon ? 'outline' : 'secondary'"
                        :class="[
                            'rounded-full px-2.5 py-1 text-xs',
                            interest.isCommon
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <Sparkles
                            v-if="interest.isCommon"
                            class="size-3"
                            aria-hidden="true"
                        />
                        {{ interest.name }}
                    </Badge>
                </div>
            </div>

            <div
                :class="[
                    'flex items-center gap-3 border-t text-sm text-muted-foreground',
                    compact ? 'pt-2' : 'pt-3',
                ]"
            >
                <Sparkles class="size-5 text-primary" aria-hidden="true" />
                <p>
                    <span class="font-medium text-foreground">{{
                        visitFrequencyLabel
                    }}</span>
                    <span v-if="profile.frequencyBonus">
                        · Même fréquence de visite</span
                    >
                </p>
            </div>

            <p id="swipe-instructions" class="sr-only">
                Balayez vers la gauche pour passer ce profil ou vers la droite
                pour l’aimer. Au clavier, utilisez les flèches gauche et droite.
            </p>
            <div
                :class="[
                    'grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]',
                    compact ? 'gap-2' : 'gap-3',
                ]"
                @pointerdown.stop
            >
                <Button
                    type="button"
                    variant="outline"
                    :class="['rounded-full', compact ? 'min-h-10' : 'min-h-12']"
                    :disabled="locked || preview"
                    aria-label="Passer ce profil"
                    @click="decide('pass')"
                >
                    <X class="size-5" aria-hidden="true" />
                    Passer
                </Button>
                <Button
                    type="button"
                    :class="[
                        'rounded-full bg-gradient-to-r from-pink-500 to-primary',
                        compact ? 'min-h-10 text-xs' : 'min-h-12',
                    ]"
                    :disabled="locked || preview"
                    aria-label="Aimer ce profil"
                    @click="decide('like')"
                >
                    <Sparkles class="size-5" aria-hidden="true" />
                    Ça m’intéresse
                </Button>
            </div>
        </div>
    </Card>
</template>
