<script setup lang="ts">
const props = defineProps<{
    labels: readonly string[];
    currentStep: number;
    furthestStep: number;
    selectable?: boolean;
}>();

const emit = defineEmits<{ select: [step: number] }>();

function selectStep(step: number): void {
    if (props.selectable !== false && step <= props.furthestStep) {
        emit('select', step);
    }
}
</script>

<template>
    <nav aria-label="Progression du profil" class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm font-semibold text-foreground">
                {{ currentStep }} sur {{ labels.length }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ labels[currentStep - 1] }}
            </p>
        </div>
        <ol
            class="grid gap-2"
            :style="{
                gridTemplateColumns: `repeat(${labels.length}, minmax(0, 1fr))`,
            }"
        >
            <li v-for="(label, index) in labels" :key="label">
                <button
                    type="button"
                    class="group block min-h-11 w-full rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-default"
                    :disabled="selectable === false || index + 1 > furthestStep"
                    :aria-label="`Étape ${index + 1} : ${label}`"
                    :aria-current="
                        currentStep === index + 1 ? 'step' : undefined
                    "
                    @click="selectStep(index + 1)"
                >
                    <span
                        class="block h-2 rounded-full transition-colors motion-reduce:transition-none"
                        :class="
                            index + 1 < currentStep
                                ? 'bg-secondary-foreground/70'
                                : index + 1 === currentStep
                                  ? 'bg-primary'
                                  : 'bg-muted'
                        "
                    />
                </button>
            </li>
        </ol>
    </nav>
</template>
