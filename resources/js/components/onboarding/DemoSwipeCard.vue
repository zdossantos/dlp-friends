<script setup lang="ts">
import { Heart, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Decision = 'pass' | 'like';

const SWIPE_THRESHOLD_PX = 72;

const props = defineProps<{
    profile: {
        displayName: string;
        bio: string;
        interests: string[];
        avatar: {
            name: string;
            imageUrl: string;
            primaryColor: string;
            secondaryColor: string;
        };
    };
    requiredDecision: Decision;
    locked?: boolean;
}>();

const emit = defineEmits<{ like: []; pass: [] }>();
const pointerStart = ref<{ id: number; x: number } | null>(null);
const offset = ref(0);

const cardStyle = computed(() => ({
    backgroundImage: `linear-gradient(145deg, ${props.profile.avatar.primaryColor}, ${props.profile.avatar.secondaryColor})`,
    transform: `translateX(${offset.value}px) rotate(${Math.max(-10, Math.min(10, offset.value / 22))}deg)`,
}));

function decide(decision: Decision): void {
    if (props.locked) {
        return;
    }

    if (decision === 'like') {
        emit('like');
    } else {
        emit('pass');
    }
}

function pointerDown(event: PointerEvent): void {
    if (props.locked) {
        return;
    }

    if ((event.target as HTMLElement).closest('button')) {
        return;
    }

    pointerStart.value = { id: event.pointerId, x: event.clientX };
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function pointerMove(event: PointerEvent): void {
    if (pointerStart.value?.id === event.pointerId) {
        offset.value = event.clientX - pointerStart.value.x;
    }
}

function pointerUp(event: PointerEvent): void {
    if (pointerStart.value?.id !== event.pointerId) {
        return;
    }

    const distance = event.clientX - pointerStart.value.x;
    pointerStart.value = null;
    offset.value = 0;

    if (distance <= -SWIPE_THRESHOLD_PX) {
        decide('pass');
    }

    if (distance >= SWIPE_THRESHOLD_PX) {
        decide('like');
    }
}
</script>

<template>
    <Card
        data-test="demo-swipe-card"
        class="w-full max-w-md touch-pan-y overflow-hidden rounded-[2rem] border-2 p-0 shadow-xl transition-transform outline-none focus-visible:ring-4 focus-visible:ring-ring/50"
        :style="cardStyle"
        tabindex="0"
        :aria-label="`Profil de démonstration de ${profile.displayName}`"
        @keydown.left.prevent="decide('pass')"
        @keydown.right.prevent="decide('like')"
        @pointerdown="pointerDown"
        @pointermove="pointerMove"
        @pointerup="pointerUp"
        @pointercancel="
            pointerStart = null;
            offset = 0;
        "
    >
        <div
            class="relative flex h-56 items-end justify-center overflow-hidden px-6 pt-4"
        >
            <div class="absolute inset-0 bg-white/15" aria-hidden="true" />
            <img
                :src="profile.avatar.imageUrl"
                :alt="`Avatar ${profile.avatar.name}`"
                class="relative z-10 max-h-52 w-full object-contain drop-shadow-2xl"
                draggable="false"
            />
        </div>
        <div class="space-y-4 bg-card p-5">
            <div>
                <h2 class="text-2xl font-semibold">
                    {{ profile.displayName }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ profile.bio }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-for="interest in profile.interests"
                    :key="interest"
                    variant="secondary"
                >
                    {{ interest }}
                </Badge>
            </div>
            <div class="grid grid-cols-2 gap-3" @pointerdown.stop>
                <Button
                    type="button"
                    variant="outline"
                    data-test="demo-pass"
                    :disabled="locked"
                    aria-label="Passer ce profil de démonstration"
                    @pointerup.stop
                    @click.stop="decide('pass')"
                >
                    <X aria-hidden="true" /> Passer
                </Button>
                <Button
                    type="button"
                    data-test="demo-like"
                    :disabled="locked"
                    aria-label="Aimer ce profil de démonstration"
                    @pointerup.stop
                    @click.stop="decide('like')"
                >
                    <Heart aria-hidden="true" /> J’aime
                </Button>
            </div>
            <p class="text-center text-xs text-muted-foreground">
                {{
                    requiredDecision === 'pass'
                        ? 'Glissez à gauche ou choisissez Passer.'
                        : 'Glissez à droite ou choisissez J’aime.'
                }}
            </p>
        </div>
    </Card>
</template>
