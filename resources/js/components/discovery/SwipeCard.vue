<script setup lang="ts">
import { ShieldCheck, Sparkles, Users, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type {
    DiscoveryCardProfile,
    SwipeDecision,
    VisitFrequency,
} from '@/types';

const SWIPE_THRESHOLD_PX = 72;
const TAP_SLOP_PX = 8;
const CONSTELLATION_START_PROGRESS = 0.42;
type AllowedDecision = SwipeDecision | 'both';

const props = withDefaults(
    defineProps<{
        profile: DiscoveryCardProfile;
        locked: boolean;
        preview?: boolean;
        compact?: boolean;
        allowedDecision?: AllowedDecision;
        publicProfileHref?: string;
        forcedDecision?: SwipeDecision;
    }>(),
    { allowedDecision: 'both', compact: false, preview: false },
);

const emit = defineEmits<{ like: []; pass: []; open: [] }>();
const { t } = useTranslations();

const visitFrequencyLabels: Record<VisitFrequency, string> = {
    rarely: t('discovery.card.frequency_rarely'),
    sometimes: t('discovery.card.frequency_sometimes'),
    often: t('discovery.card.frequency_often'),
    very_often: t('discovery.card.frequency_very_often'),
};

const pointerStart = ref<{
    pointerId: number;
    x: number;
    y: number;
} | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const suppressNextClick = ref(false);
const exitDirection = ref<-1 | 0 | 1>(0);
const constellationStars = [
    { x: 10, y: 8, size: 3 },
    { x: 23, y: 13, size: 2 },
    { x: 39, y: 7, size: 4 },
    { x: 56, y: 16, size: 2 },
    { x: 72, y: 9, size: 3 },
    { x: 88, y: 19, size: 2 },
    { x: 16, y: 27, size: 2 },
    { x: 31, y: 34, size: 3 },
    { x: 49, y: 28, size: 2 },
    { x: 66, y: 37, size: 4 },
    { x: 83, y: 31, size: 2 },
    { x: 8, y: 48, size: 3 },
    { x: 24, y: 56, size: 2 },
    { x: 43, y: 47, size: 3 },
    { x: 58, y: 59, size: 2 },
    { x: 78, y: 51, size: 3 },
    { x: 91, y: 62, size: 2 },
    { x: 13, y: 72, size: 2 },
    { x: 29, y: 81, size: 4 },
    { x: 47, y: 70, size: 2 },
    { x: 63, y: 84, size: 3 },
    { x: 81, y: 76, size: 2 },
    { x: 92, y: 88, size: 3 },
    { x: 51, y: 93, size: 2 },
    { x: 18, y: 91, size: 2 },
    { x: 35, y: 65, size: 3 },
    { x: 70, y: 68, size: 2 },
    { x: 87, y: 43, size: 3 },
    { x: 5, y: 84, size: 2 },
    { x: 75, y: 95, size: 2 },
    { x: 95, y: 5, size: 2 },
    { x: 54, y: 40, size: 3 },
] as const;

const likeProgress = computed(() => {
    if (props.forcedDecision === 'like') {
        return 1;
    }

    const dragProgress = Math.max(
        0,
        Math.min(1, dragOffset.value.x / SWIPE_THRESHOLD_PX),
    );

    return Math.max(
        0,
        (dragProgress - CONSTELLATION_START_PROGRESS) /
            (1 - CONSTELLATION_START_PROGRESS),
    );
});

const constellationStyle = computed(() => {
    const progress = likeProgress.value;

    return {
        opacity: (progress * 0.7).toFixed(3),
        transform: `scale(${0.94 + progress * 0.06})`,
    };
});

const cardStyle = computed(() => {
    const rotation = Math.max(-14, Math.min(14, dragOffset.value.x / 20));
    const transform = exitDirection.value
        ? `translate3d(${exitDirection.value * 120}vw, ${dragOffset.value.y}px, 0) rotate(${exitDirection.value * 18}deg)`
        : `translate3d(${dragOffset.value.x}px, ${dragOffset.value.y}px, 0) rotate(${rotation}deg)`;

    return {
        opacity: exitDirection.value ? '0' : '1',
        transform,
    };
});

const visitFrequencyLabel = computed(() => {
    return props.profile.visitFrequency
        ? visitFrequencyLabels[props.profile.visitFrequency]
        : t('discovery.card.frequency_unknown');
});

const avatarGradient = computed(() => ({
    backgroundImage: `linear-gradient(145deg, ${props.profile.avatar.primary_color}, ${props.profile.avatar.secondary_color})`,
}));

function decide(decision: SwipeDecision) {
    if (
        props.locked ||
        props.preview ||
        exitDirection.value !== 0 ||
        !canDecide(decision)
    ) {
        return;
    }

    if (decision === 'like') {
        emit('like');

        return;
    }

    emit('pass');
}

function animateDecision(decision: SwipeDecision) {
    if (
        props.locked ||
        props.preview ||
        exitDirection.value !== 0 ||
        !canDecide(decision)
    ) {
        return;
    }

    isDragging.value = false;
    exitDirection.value = decision === 'like' ? 1 : -1;

    if (decision === 'like') {
        emit('like');
    } else {
        emit('pass');
    }
}

function canDecide(decision: SwipeDecision): boolean {
    return (
        props.allowedDecision === 'both' || props.allowedDecision === decision
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

    if (event.isPrimary) {
        target?.setPointerCapture?.(event.pointerId);
    }
}

function handlePointerMove(event: PointerEvent) {
    const start = pointerStart.value;

    if (start === null || start.pointerId !== event.pointerId) {
        return;
    }

    const rawDeltaX = event.clientX - start.x;
    const deltaY = event.clientY - start.y;

    if (Math.hypot(rawDeltaX, deltaY) > TAP_SLOP_PX) {
        suppressNextClick.value = true;
    }

    const deltaX =
        (rawDeltaX > 0 && !canDecide('like')) ||
        (rawDeltaX < 0 && !canDecide('pass'))
            ? 0
            : rawDeltaX;

    dragOffset.value = { x: deltaX, y: deltaY * 0.15 };

    if (Math.abs(deltaX) > Math.abs(deltaY)) {
        event.preventDefault();
    }
}

function openPublicProfile(event: MouseEvent) {
    if (
        !props.publicProfileHref ||
        props.preview ||
        (event.target as HTMLElement | null)?.closest(
            'a, button, input, select, textarea',
        )
    ) {
        return;
    }

    if (suppressNextClick.value) {
        suppressNextClick.value = false;

        return;
    }

    emit('open');
}

function forgetPointerStart(event?: PointerEvent) {
    if (
        event &&
        pointerStart.value !== null &&
        pointerStart.value.pointerId !== event.pointerId
    ) {
        return;
    }

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

    if (deltaX <= -SWIPE_THRESHOLD_PX && canDecide('pass')) {
        animateDecision('pass');

        return;
    }

    if (deltaX >= SWIPE_THRESHOLD_PX && canDecide('like')) {
        animateDecision('like');

        return;
    }

    dragOffset.value = { x: 0, y: 0 };
}

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
    <div
        data-test="discovery-swipe-surface"
        class="flex h-full max-h-full w-full max-w-md flex-col gap-5"
    >
        <Card
            :data-test="
                forcedDecision ? 'discovery-exiting-card' : 'discovery-card'
            "
            :data-decision="forcedDecision"
            :data-exit-direction="
                forcedDecision === 'like'
                    ? 'right'
                    : forcedDecision === 'pass'
                      ? 'left'
                      : undefined
            "
            class="relative flex min-h-0 w-full flex-1 touch-pan-y flex-col gap-0 overflow-hidden rounded-[2rem] p-0 shadow-xl shadow-primary/10 transition-[transform,opacity] ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:duration-0"
            :class="[
                isDragging ? 'duration-0' : 'duration-[280ms]',
                forcedDecision === 'like' && 'motion-card-exit-right',
                forcedDecision === 'pass' && 'motion-card-exit-left',
                profile.isAdmin
                    ? 'border-2 border-amber-400'
                    : 'border-border/70',
            ]"
            :style="cardStyle"
            :tabindex="preview ? -1 : 0"
            :aria-label="
                t('discovery.card.label', { name: profile.displayName })
            "
            aria-describedby="swipe-instructions"
            @keydown.left.self.prevent.stop="decide('pass')"
            @keydown.right.self.prevent.stop="decide('like')"
            @keydown.enter.self.prevent="publicProfileHref && emit('open')"
            @pointerdown="rememberPointerStart"
            @pointermove="handlePointerMove"
            @pointerup="handlePointerEnd"
            @pointercancel="resetCard"
            @lostpointercapture="forgetPointerStart"
            @click="openPublicProfile"
        >
            <Badge
                v-if="profile.isAdmin"
                data-test="admin-discovery-badge"
                class="absolute top-3 right-3 z-40 gap-1 border-amber-400 bg-amber-50 text-amber-950 shadow-md"
                variant="outline"
            >
                <ShieldCheck class="size-4" aria-hidden="true" />
                {{ t('profile.details.administrator') }}
            </Badge>
            <div
                data-test="discovery-like-constellation"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 z-30 overflow-hidden rounded-[2rem] motion-reduce:hidden"
                :style="constellationStyle"
            >
                <span
                    v-for="(star, index) in constellationStars"
                    :key="index"
                    data-test="discovery-star"
                    class="absolute rotate-45 rounded-[1px] bg-amber-100 shadow-[0_0_5px_rgba(252,211,77,.55)]"
                    :style="{
                        left: `${star.x}%`,
                        top: `${star.y}%`,
                        width: `${star.size}px`,
                        height: `${star.size}px`,
                    }"
                />
            </div>
            <div
                data-test="discovery-avatar-hero"
                :class="[
                    'relative flex shrink-0 items-end justify-center overflow-hidden px-6 pt-4',
                    compact
                        ? 'h-40 px-5 pt-3'
                        : 'h-[clamp(10rem,23svh,12rem)] sm:h-64',
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
                    :alt="
                        t('discovery.card.avatar_alt', {
                            name: profile.avatar.name,
                        })
                    "
                    draggable="false"
                    :class="[
                        'pointer-events-none relative z-10 w-full object-contain drop-shadow-2xl select-none',
                        compact ? 'max-h-40' : 'max-h-[11rem] sm:max-h-[15rem]',
                    ]"
                />
            </div>

            <div
                data-test="discovery-information-sheet"
                :class="[
                    'relative z-20 flex min-h-0 flex-1 flex-col rounded-t-[2rem] bg-card',
                    compact ? '-mt-5 px-4 pt-4 pb-3' : '-mt-6 px-4 pt-3 pb-3',
                ]"
            >
                <div
                    data-test="discovery-identity"
                    class="flex min-h-8 items-center gap-2 overflow-hidden"
                >
                    <h2
                        :class="[
                            'min-w-0 truncate font-semibold tracking-tight',
                            compact ? 'text-2xl' : 'text-2xl sm:text-3xl',
                        ]"
                        :title="profile.displayName"
                    >
                        {{ profile.displayName }}
                    </h2>
                    <Badge
                        class="shrink-0 rounded-full px-3 py-1"
                        variant="secondary"
                    >
                        {{ t('discovery.card.age', { age: profile.age }) }}
                    </Badge>
                </div>
                <div
                    data-test="discovery-affinities"
                    class="mt-1.5 shrink-0 space-y-1"
                >
                    <p
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <Users
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{
                            profile.commonInterestCount > 1
                                ? t('discovery.card.common_interests', {
                                      count: profile.commonInterestCount,
                                  })
                                : t('discovery.card.common_interest', {
                                      count: profile.commonInterestCount,
                                  })
                        }}
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="interest in profile.interests"
                            :key="interest.name"
                            data-test="discovery-interest"
                            :data-common="interest.isCommon"
                            :variant="
                                interest.isCommon ? 'outline' : 'secondary'
                            "
                            :class="[
                                'max-w-full rounded-full px-2 py-0.5 text-xs leading-4',
                                interest.isCommon
                                    ? 'border-primary/30 bg-primary/10 text-primary'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            <Sparkles
                                v-if="interest.isCommon"
                                class="size-3 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ interest.name }}</span>
                        </Badge>
                    </div>
                </div>

                <p
                    data-test="discovery-bio"
                    class="mt-1.5 line-clamp-2 h-9 shrink-0 overflow-hidden text-sm leading-[1.125rem] text-muted-foreground"
                >
                    {{ profile.bio ?? t('discovery.card.empty_bio') }}
                </p>

                <div
                    data-test="discovery-frequency"
                    class="mt-auto flex min-h-9 items-center gap-3 border-t pt-2 text-sm text-muted-foreground"
                >
                    <Sparkles
                        class="size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p class="min-w-0 truncate">
                        <span class="font-medium text-foreground">{{
                            visitFrequencyLabel
                        }}</span>
                    </p>
                </div>

                <p id="swipe-instructions" class="sr-only">
                    <template v-if="allowedDecision === 'pass'">
                        {{ t('discovery.card.instructions_pass') }}
                    </template>
                    <template v-else-if="allowedDecision === 'like'">
                        {{ t('discovery.card.instructions_discover') }}
                    </template>
                    <template v-else>
                        {{ t('discovery.card.instructions_both') }}
                    </template>
                </p>
            </div>
        </Card>

        <div
            v-if="!preview"
            data-test="discovery-actions"
            class="flex h-[4.75rem] shrink-0 items-start justify-center gap-10 pt-1"
            :aria-label="t('discovery.card.actions_label')"
            @pointerdown.stop
        >
            <div class="flex flex-col items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    class="size-12 rounded-full border-2 bg-background shadow-sm focus-visible:ring-[3px]"
                    :disabled="locked || !canDecide('pass')"
                    :aria-label="t('discovery.actions.pass_profile')"
                    :title="t('discovery.actions.pass_profile')"
                    @click="decide('pass')"
                >
                    <X class="size-5" aria-hidden="true" />
                    <span class="sr-only">{{
                        t('discovery.actions.pass_profile')
                    }}</span>
                </Button>
                <span
                    class="text-xs font-medium text-muted-foreground"
                    aria-hidden="true"
                >
                    {{ t('discovery.actions.pass') }}
                </span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <Button
                    type="button"
                    class="size-12 rounded-full bg-gradient-to-br from-primary to-primary/75 shadow-sm focus-visible:ring-[3px]"
                    :disabled="locked || !canDecide('like')"
                    :aria-label="t('discovery.actions.discover_profile')"
                    :title="t('discovery.actions.discover_profile')"
                    @click="decide('like')"
                >
                    <Sparkles class="size-5" aria-hidden="true" />
                    <span class="sr-only">{{
                        t('discovery.actions.discover_profile')
                    }}</span>
                </Button>
                <span
                    class="text-xs font-medium text-muted-foreground"
                    aria-hidden="true"
                >
                    {{ t('discovery.actions.discover') }}
                </span>
            </div>
        </div>
        <div
            v-else-if="!compact"
            class="h-[4.75rem] shrink-0"
            aria-hidden="true"
        />
    </div>
</template>
