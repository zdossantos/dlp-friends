<script setup lang="ts">
import { computed, ref } from 'vue';
import type { InterestOption } from '@/types';

const props = defineProps<{
    interests: InterestOption[];
    selectedIds: number[];
    limit: number;
}>();

const selected = ref(new Set(props.selectedIds));
const count = computed(() => selected.value.size);

function toggle(id: number): void {
    const next = new Set(selected.value);

    if (next.has(id)) {
        next.delete(id);
    } else if (next.size < props.limit) {
        next.add(id);
    }

    selected.value = next;
}
</script>

<template>
    <fieldset class="grid gap-3">
        <div class="flex items-center justify-between gap-3">
            <legend class="font-medium">Mes intérêts</legend>
            <span aria-live="polite">{{ count }} / {{ limit }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="interest in interests"
                :key="interest.id"
                type="button"
                :aria-pressed="selected.has(interest.id)"
                :aria-label="`${selected.has(interest.id) ? 'Retirer' : 'Ajouter'} ${interest.name}`"
                :disabled="count >= limit && !selected.has(interest.id)"
                :class="[
                    'rounded-full border px-3 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                    selected.has(interest.id)
                        ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                        : 'border-input bg-background text-foreground hover:bg-accent hover:text-accent-foreground',
                ]"
                @click="toggle(interest.id)"
            >
                {{ interest.name }}
            </button>
        </div>
        <input
            v-for="id in selected"
            :key="id"
            type="hidden"
            name="interest_ids[]"
            :value="id"
        />
    </fieldset>
</template>
