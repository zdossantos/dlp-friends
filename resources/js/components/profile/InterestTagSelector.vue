<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { InterestOption } from '@/types';

const props = defineProps<{
    interests: InterestOption[];
    selectedIds: number[];
    limit: number;
}>();

function selectedFromProps(): Set<number> {
    const availableIds = new Set(
        props.interests.map((interest) => interest.id),
    );

    return new Set(props.selectedIds.filter((id) => availableIds.has(id)));
}

const selected = ref(selectedFromProps());
const count = computed(() => selected.value.size);

watch([() => props.selectedIds, () => props.interests], () => {
    selected.value = selectedFromProps();
});

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
        <legend class="flex w-full items-center justify-between gap-3">
            <span class="font-medium">Mes intérêts</span>
            <span aria-live="polite">{{ count }} / {{ limit }}</span>
        </legend>
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
