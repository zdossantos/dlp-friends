<script setup lang="ts">
import { Check, ChevronLeft, ChevronRight, MoveHorizontal } from '@lucide/vue';
import type { CSSProperties } from 'vue';
import { computed, ref } from 'vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AvatarOption } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    avatars: AvatarOption[];
    modelValue: number | null;
}>();
const emit = defineEmits<{ 'update:modelValue': [id: number] }>();
const pointerStart = ref<{ id: number; x: number } | null>(null);
const didSwipe = ref(false);
const selectedIndex = computed(() => {
    const index = props.avatars.findIndex(
        (avatar) => avatar.id === props.modelValue,
    );

    return index >= 0 ? index : 0;
});
const selectedAvatar = computed(() => props.avatars[selectedIndex.value]);

function select(index: number): void {
    const avatar =
        props.avatars[(index + props.avatars.length) % props.avatars.length];

    if (avatar) {
        emit('update:modelValue', avatar.id);
    }
}
function previous(): void {
    select(selectedIndex.value - 1);
}
function next(): void {
    select(selectedIndex.value + 1);
}
function relativePosition(index: number): number {
    const count = props.avatars.length;
    let position = index - selectedIndex.value;

    if (position > count / 2) {
        position -= count;
    }

    if (position < -count / 2) {
        position += count;
    }

    return position;
}
function itemStyle(index: number): CSSProperties {
    const position = relativePosition(index);
    const direction = Math.sign(position);

    if (position === 0) {
        return {
            opacity: '1',
            pointerEvents: 'auto',
            transform: 'translate3d(0, 0, 0) scale(1) rotate(0deg)',
            zIndex: 20,
        };
    }

    if (Math.abs(position) === 1) {
        return {
            opacity: '0.58',
            pointerEvents: 'auto',
            transform: `translate3d(${direction * 45}%, 0, 0) scale(.82) rotate(${direction * 7}deg)`,
            zIndex: 10,
        };
    }

    return {
        opacity: '0',
        pointerEvents: 'none',
        transform: `translate3d(${direction * 145}%, 0, 0) scale(.7) rotate(${direction * 11}deg)`,
        zIndex: 0,
    };
}
function isVisible(index: number): boolean {
    return Math.abs(relativePosition(index)) <= 1;
}
function chooseAvatar(avatar: AvatarOption): void {
    if (didSwipe.value) {
        didSwipe.value = false;

        return;
    }

    emit('update:modelValue', avatar.id);
}
function startPointer(event: PointerEvent): void {
    didSwipe.value = false;
    pointerStart.value = { id: event.pointerId, x: event.clientX };

    try {
        (event.currentTarget as HTMLElement).setPointerCapture?.(
            event.pointerId,
        );
    } catch {
        // Synthetic browser tests do not own an active pointer to capture.
    }
}
function endPointer(event: PointerEvent): void {
    if (pointerStart.value?.id !== event.pointerId) {
        return;
    }

    const distance = event.clientX - pointerStart.value.x;
    pointerStart.value = null;

    if (distance <= -48) {
        didSwipe.value = true;
        next();
    } else if (distance >= 48) {
        didSwipe.value = true;
        previous();
    }

    if (didSwipe.value) {
        window.setTimeout(() => {
            didSwipe.value = false;
        }, 0);
    }
}
function cancelPointer(): void {
    pointerStart.value = null;
    didSwipe.value = false;
}
</script>

<template>
    <div
        v-if="selectedAvatar"
        data-test="avatar-carousel"
        class="flex min-h-0 flex-1 touch-pan-y flex-col rounded-[2rem] select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:outline-none"
        tabindex="0"
        role="group"
        :aria-roledescription="t('profile.avatar.carousel_role')"
        :aria-label="
            t('profile.avatar.selected_avatar', { name: selectedAvatar.name })
        "
        @keydown.left.prevent="previous"
        @keydown.right.prevent="next"
    >
        <div
            class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden py-1"
        >
            <button
                v-for="(avatar, index) in avatars"
                :key="avatar.id"
                type="button"
                :data-test="`avatar-carousel-item-${avatar.id}`"
                class="absolute w-[66%] max-w-72 transition-[transform,opacity] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none sm:max-w-80"
                :class="
                    avatar.id === modelValue
                        ? 'cursor-default'
                        : 'hover:opacity-80'
                "
                :style="itemStyle(index)"
                :tabindex="isVisible(index) ? 0 : -1"
                :aria-hidden="isVisible(index) ? undefined : 'true'"
                :aria-label="
                    avatar.id === modelValue
                        ? t('profile.avatar.selected', { name: avatar.name })
                        : t('profile.avatar.choose', { name: avatar.name })
                "
                @pointerdown="startPointer"
                @pointerup="endPointer"
                @pointercancel="cancelPointer"
                @lostpointercapture="pointerStart = null"
                @click="chooseAvatar(avatar)"
            >
                <AvatarPortrait
                    :avatar="avatar"
                    :data-test="
                        avatar.id === modelValue
                            ? `avatar-option-${avatar.id}`
                            : undefined
                    "
                    class="aspect-[4/5] rounded-[2rem] border-[3px] border-card shadow-2xl shadow-primary/20"
                    :class="
                        avatar.id === modelValue ? 'ring-2 ring-primary/60' : ''
                    "
                />
                <span
                    v-if="avatar.id === modelValue"
                    class="absolute right-3 bottom-3 flex items-center gap-2 rounded-full border border-white/70 bg-background/95 px-3 py-2 text-xs font-semibold text-foreground shadow-lg backdrop-blur"
                >
                    <Check class="size-4" aria-hidden="true" />
                    {{ t('profile.avatar.selected_badge') }}
                </span>
            </button>

            <Button
                v-if="avatars.length > 1"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute left-2 z-30 size-11 rounded-full shadow-lg sm:left-4"
                :aria-label="t('profile.avatar.previous')"
                @pointerdown.stop
                @click.stop="previous"
            >
                <ChevronLeft class="size-6" aria-hidden="true" />
            </Button>
            <Button
                v-if="avatars.length > 1"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute right-2 z-30 size-11 rounded-full shadow-lg sm:right-4"
                :aria-label="t('profile.avatar.next')"
                @pointerdown.stop
                @click.stop="next"
            >
                <ChevronRight class="size-6" aria-hidden="true" />
            </Button>
        </div>

        <div class="shrink-0 space-y-1.5 text-center" aria-live="polite">
            <p class="text-xl font-semibold tracking-tight">
                {{ selectedAvatar.name }}
            </p>
            <div class="flex justify-center gap-2" aria-hidden="true">
                <span
                    v-for="avatar in avatars"
                    :key="avatar.id"
                    class="size-2 rounded-full transition-colors motion-reduce:transition-none"
                    :class="
                        avatar.id === modelValue ? 'bg-primary' : 'bg-muted'
                    "
                />
            </div>
            <p
                class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
            >
                <MoveHorizontal class="size-4" aria-hidden="true" />
                {{ t('profile.avatar.instructions') }}
            </p>
        </div>

        <input
            v-for="avatar in avatars"
            :key="avatar.id"
            class="sr-only"
            type="radio"
            name="avatar_id"
            :value="avatar.id"
            :checked="avatar.id === modelValue"
            required
            tabindex="-1"
        />
    </div>
</template>
