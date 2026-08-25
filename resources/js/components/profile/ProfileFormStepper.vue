<script setup lang="ts">
const props = defineProps<{
    currentStep: number;
    furthestStep: number;
}>();

const emit = defineEmits<{ select: [step: number] }>();

const labels = ['Avatar', 'Identité', 'Affinités', 'Aperçu'];

function selectStep(step: number): void {
    if (step <= props.furthestStep) {
        emit('select', step);
    }
}
</script>

<template>
    <nav aria-label="Progression du profil" class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm font-semibold text-foreground">
                {{ currentStep }} sur 4
            </p>
            <p class="text-sm text-muted-foreground">
                {{ labels[currentStep - 1] }}
            </p>
        </div>
        <ol class="grid grid-cols-4 gap-2">
            <li v-for="(label, index) in labels" :key="label">
                <button
                    type="button"
                    class="group block min-h-11 w-full rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-default"
                    :disabled="index + 1 > furthestStep"
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
