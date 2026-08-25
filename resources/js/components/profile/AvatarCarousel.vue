<script setup lang="ts">
import { Check, ChevronLeft, ChevronRight, MoveHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Button } from '@/components/ui/button';
import type { AvatarOption } from '@/types';

const props = defineProps<{
    avatars: AvatarOption[];
    modelValue: number | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [id: number] }>();

const pointerStart = ref<{ id: number; x: number } | null>(null);
const selectedIndex = computed(() => {
    const index = props.avatars.findIndex(
        (avatar) => avatar.id === props.modelValue,
    );

    return index >= 0 ? index : 0;
});
const selectedAvatar = computed(() => props.avatars[selectedIndex.value]);
const previousAvatar = computed(() =>
    props.avatars.length > 1
        ? props.avatars[
              (selectedIndex.value - 1 + props.avatars.length) %
                  props.avatars.length
          ]
        : null,
);
const nextAvatar = computed(() =>
    props.avatars.length > 1
        ? props.avatars[(selectedIndex.value + 1) % props.avatars.length]
        : null,
);

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

function startPointer(event: PointerEvent): void {
    pointerStart.value = { id: event.pointerId, x: event.clientX };
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function endPointer(event: PointerEvent): void {
    if (pointerStart.value?.id !== event.pointerId) {
        return;
    }

    const distance = event.clientX - pointerStart.value.x;
    pointerStart.value = null;

    if (distance <= -48) {
        next();
    } else if (distance >= 48) {
        previous();
    }
}
</script>

<template>
    <div
        v-if="selectedAvatar"
        data-test="avatar-carousel"
        class="touch-pan-y space-y-5 rounded-[2rem] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:outline-none"
        tabindex="0"
        role="group"
        aria-roledescription="carrousel"
        :aria-label="`Avatar sélectionné : ${selectedAvatar.name}`"
        @keydown.left.prevent="previous"
        @keydown.right.prevent="next"
        @pointerdown="startPointer"
        @pointerup="endPointer"
        @pointercancel="pointerStart = null"
    >
        <div
            class="relative flex min-h-[23rem] items-center justify-center overflow-hidden py-2 sm:min-h-[27rem]"
        >
            <button
                v-if="previousAvatar"
                type="button"
                class="absolute -left-[42%] w-[72%] opacity-55 transition-[transform,opacity] duration-300 hover:opacity-80 focus-visible:z-20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none sm:-left-[34%]"
                :aria-label="`Choisir ${previousAvatar.name}`"
                @click="previous"
            >
                <AvatarPortrait
                    :avatar="previousAvatar"
                    class="rounded-[2rem]"
                />
            </button>

            <div class="relative z-10 w-[72%] max-w-72 sm:max-w-80">
                <AvatarPortrait
                    :avatar="selectedAvatar"
                    :data-test="`avatar-option-${selectedAvatar.id}`"
                    class="aspect-[4/5] rounded-[2rem] border-[3px] border-card shadow-2xl ring-2 shadow-primary/20 ring-primary/60"
                />
                <span
                    class="absolute right-4 bottom-4 flex items-center gap-2 rounded-full border border-white/70 bg-background/95 px-3 py-2 text-sm font-semibold text-foreground shadow-lg backdrop-blur"
                >
                    <Check class="size-4" aria-hidden="true" />
                    Sélectionné
                </span>
            </div>

            <button
                v-if="nextAvatar"
                type="button"
                class="absolute -right-[42%] w-[72%] opacity-55 transition-[transform,opacity] duration-300 hover:opacity-80 focus-visible:z-20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none sm:-right-[34%]"
                :aria-label="`Choisir ${nextAvatar.name}`"
                @click="next"
            >
                <AvatarPortrait :avatar="nextAvatar" class="rounded-[2rem]" />
            </button>

            <Button
                v-if="previousAvatar"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute left-2 z-20 size-12 rounded-full shadow-lg sm:left-4"
                aria-label="Avatar précédent"
                @click.stop="previous"
            >
                <ChevronLeft class="size-6" aria-hidden="true" />
            </Button>
            <Button
                v-if="nextAvatar"
                type="button"
                variant="secondary"
                size="icon"
                class="absolute right-2 z-20 size-12 rounded-full shadow-lg sm:right-4"
                aria-label="Avatar suivant"
                @click.stop="next"
            >
                <ChevronRight class="size-6" aria-hidden="true" />
            </Button>
        </div>

        <div class="space-y-3 text-center">
            <p class="text-2xl font-semibold tracking-tight">
                {{ selectedAvatar.name }}
            </p>
            <div class="flex justify-center gap-2" aria-hidden="true">
                <span
                    v-for="avatar in avatars"
                    :key="avatar.id"
                    class="size-2 rounded-full"
                    :class="
                        avatar.id === modelValue ? 'bg-primary' : 'bg-muted'
                    "
                />
            </div>
            <p
                class="flex items-center justify-center gap-2 text-sm text-muted-foreground"
            >
                <MoveHorizontal class="size-4" aria-hidden="true" />
                Balayez ou utilisez les flèches
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
