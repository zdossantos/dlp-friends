<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import DiscoveryPreferenceController from '@/actions/App/Http/Controllers/Settings/DiscoveryPreferenceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    preferences: {
        min_age: number;
        max_age: number;
        min_common_interests: number;
    };
}>();
const { t } = useTranslations();
const form = useForm({ ...props.preferences });
const ageRange = computed(() => [form.min_age, form.max_age]);

function updateAgeRange(values: number[] | undefined) {
    if (values?.length === 2) {
        [form.min_age, form.max_age] = values;
    }
}

function submit() {
    form.patch(DiscoveryPreferenceController.update().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="space-y-6" aria-labelledby="discovery-preferences-title">
        <Heading
            id="discovery-preferences-title"
            variant="small"
            :title="t('account.settings.discovery.title')"
            :description="t('account.settings.discovery.description')"
        />
        <form class="space-y-6" @submit.prevent="submit">
            <fieldset class="grid min-w-0 gap-2" :disabled="form.processing">
                <legend class="text-sm font-medium">
                    {{ t('account.settings.discovery.age_range') }}
                </legend>
                <p
                    id="discovery-age-help"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('account.settings.discovery.age_help') }}
                </p>
                <output class="font-medium" aria-live="polite">
                    {{
                        t('account.settings.discovery.age_value', {
                            min: form.min_age,
                            max: form.max_age,
                        })
                    }}
                </output>
                <Slider
                    id="discovery-age-range"
                    :model-value="ageRange"
                    :min="18"
                    :max="120"
                    :step="1"
                    :disabled="form.processing"
                    :thumb-labels="[
                        t('account.settings.discovery.min_age'),
                        t('account.settings.discovery.max_age'),
                    ]"
                    aria-describedby="discovery-age-help"
                    @update:model-value="updateAgeRange"
                />
                <InputError :message="form.errors.min_age" />
                <InputError :message="form.errors.max_age" />
            </fieldset>
            <div class="grid gap-2">
                <Label for="min_common_interests">{{
                    t('account.settings.discovery.min_common_interests')
                }}</Label>
                <Input
                    id="min_common_interests"
                    v-model="form.min_common_interests"
                    name="min_common_interests"
                    type="number"
                    min="0"
                    max="100"
                    step="1"
                    required
                    :disabled="form.processing"
                    aria-describedby="discovery-common-help"
                    :aria-invalid="!!form.errors.min_common_interests"
                />
                <p
                    id="discovery-common-help"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('account.settings.discovery.common_help') }}
                </p>
                <InputError :message="form.errors.min_common_interests" />
            </div>
            <Button
                :disabled="form.processing"
                :aria-busy="form.processing"
                data-test="save-discovery-preferences"
            >
                <Spinner v-if="form.processing" />
                {{ t('account.settings.discovery.save') }}
            </Button>
        </form>
    </section>
</template>
