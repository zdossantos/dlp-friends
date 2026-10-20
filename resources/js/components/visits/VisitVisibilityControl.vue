<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, LockKeyhole, Users } from '@lucide/vue';
import { ref } from 'vue';
import ResponsiveConfirmation from '@/components/common/ResponsiveConfirmation.vue';
import InputError from '@/components/InputError.vue';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/visits/visibility';
import type { VisitsVisibility } from '@/types/visit';
const props = defineProps<{ visibility: VisitsVisibility }>();
const { t } = useTranslations();
const confirming = ref(false);
const form = useForm({ visibility: props.visibility, confirmed: false });
function save(visibility: VisitsVisibility, confirmed = false) {
    form.visibility = visibility;
    form.confirmed = confirmed;
    form.patch(update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
        },
    });
}
function change(event: Event) {
    const select = event.target as HTMLSelectElement;
    const next = select.value as VisitsVisibility;
    select.value = props.visibility;

    if (next === 'members' && props.visibility !== 'members') {
        confirming.value = true;
    } else {
        save(next);
    }
}
</script>
<template>
    <section class="rounded-2xl border border-border bg-card p-4">
        <div
            class="grid grid-cols-[auto_1fr] items-start gap-3 sm:grid-cols-[auto_1fr_auto] sm:items-center"
        >
            <component
                :is="
                    visibility === 'hidden'
                        ? LockKeyhole
                        : visibility === 'matches'
                          ? Users
                          : Eye
                "
                class="size-5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <label for="visit-visibility" class="font-semibold">{{
                    t('visits.visibility_label')
                }}</label>
                <p
                    id="visit-visibility-help"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ t('visits.visibility_help') }}
                </p>
            </div>
            <select
                id="visit-visibility"
                :value="visibility"
                :disabled="form.processing"
                aria-describedby="visit-visibility-help visit-visibility-error"
                class="col-span-2 min-h-11 w-full max-w-full rounded-xl border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring sm:col-span-1 sm:w-auto"
                @change="change"
            >
                <option
                    v-for="value in ['hidden', 'matches', 'members']"
                    :key="value"
                    :value="value"
                >
                    {{ t(`visits.visibility.${value}`) }}
                </option>
            </select>
        </div>
        <InputError
            id="visit-visibility-error"
            :message="form.errors.confirmed ?? form.errors.visibility"
        />
        <ResponsiveConfirmation
            :open="confirming"
            :title="t('visits.share_title')"
            :description="t('visits.share_description')"
            :confirm-label="t('visits.share_confirm')"
            :busy="form.processing"
            test-prefix="visit-confirm"
            @update:open="confirming = $event"
            @confirm="save('members', true)"
        />
    </section>
</template>
