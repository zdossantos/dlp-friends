<script setup lang="ts">
import { MapPin, LoaderCircle } from '@lucide/vue';
import {
    ComboboxRoot,
    ComboboxAnchor,
    ComboboxInput,
    ComboboxContent,
    ComboboxPortal,
    ComboboxItem,
} from 'reka-ui';
import { onBeforeUnmount, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
const props = defineProps<{
    id: string;
    modelValue: string;
    disabled?: boolean;
    invalid?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { t } = useTranslations();
const open = ref(false);
const suggestions = ref<string[]>([]);
const status = ref<'idle' | 'loading' | 'ready' | 'unavailable'>('idle');
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
function cancel() {
    clearTimeout(timer);
    controller?.abort();
}
function select(value: unknown) {
    if (typeof value !== 'string') {
        return;
    }

    cancel();
    emit('update:modelValue', value);
    suggestions.value = [];
    status.value = 'idle';
    open.value = false;
}
function search(value: string | number) {
    const text = String(value);
    emit('update:modelValue', text);
    cancel();
    suggestions.value = [];
    status.value = 'idle';

    if (props.disabled || text.trim().length < 3) {
        return;
    }

    status.value = 'loading';
    timer = setTimeout(async () => {
        const request = new AbortController();
        controller = request;

        try {
            const response = await fetch('/trips/places', {
                method: 'POST',
                credentials: 'same-origin',
                signal: request.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...xsrfHeader(document.cookie),
                },
                body: JSON.stringify({ query: text.trim() }),
            });

            if (!response.ok) {
                throw new Error('unavailable');
            }

            const data: unknown = await response.json();

            if (request.signal.aborted) {
                return;
            }

            if (
                !data ||
                typeof data !== 'object' ||
                !('suggestions' in data) ||
                !Array.isArray(data.suggestions)
            ) {
                throw new Error('unavailable');
            }

            suggestions.value = data.suggestions
                .filter((item): item is string => typeof item === 'string')
                .slice(0, 5);
            status.value = 'ready';
        } catch {
            if (!request.signal.aborted) {
                status.value = 'unavailable';
            }
        }
    }, 350);
}
onBeforeUnmount(cancel);
</script>
<template>
    <ComboboxRoot
        v-model:open="open"
        :disabled="disabled"
        :reset-search-term-on-blur="false"
        :reset-search-term-on-select="false"
        ignore-filter
        class="relative min-w-0"
        @update:model-value="select"
    >
        <ComboboxAnchor class="relative">
            <MapPin
                class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <ComboboxInput as-child :model-value="modelValue">
                <Input
                    :id="id"
                    :model-value="modelValue"
                    :disabled="disabled"
                    @update:model-value="search"
                    :aria-invalid="invalid"
                    :aria-describedby="`${id}-error ${id}-place-hint`"
                    :placeholder="t('trips.place_search.placeholder')"
                    maxlength="160"
                    required
                    class="min-h-11 pl-10"
                />
            </ComboboxInput>
        </ComboboxAnchor>
        <ComboboxPortal>
            <ComboboxContent
                v-if="status !== 'idle'"
                position="popper"
                :side-offset="4"
                :collision-padding="16"
                class="z-[70] max-h-[min(20rem,var(--reka-combobox-content-available-height))] w-[var(--reka-combobox-trigger-width)] overflow-y-auto rounded-xl border border-border bg-popover text-popover-foreground shadow-md"
            >
                <p
                    v-if="status === 'loading'"
                    role="status"
                    class="flex items-center gap-2 px-3 py-3 text-sm text-muted-foreground"
                >
                    <LoaderCircle
                        class="size-4 motion-safe:animate-spin"
                        aria-hidden="true"
                    />{{ t('trips.place_search.loading') }}
                </p>
                <p
                    v-else-if="status === 'unavailable'"
                    role="status"
                    class="px-3 py-3 text-sm text-muted-foreground"
                >
                    {{ t('trips.place_search.unavailable') }}
                </p>
                <p
                    v-else-if="suggestions.length === 0"
                    role="status"
                    class="px-3 py-3 text-sm text-muted-foreground"
                >
                    {{ t('trips.place_search.empty') }}
                </p>
                <ComboboxItem
                    v-for="(suggestion, index) in suggestions"
                    :key="suggestion"
                    :value="suggestion"
                    @select.prevent="select(suggestion)"
                    :data-test="`${id}-place-${index}`"
                    class="flex min-h-11 cursor-pointer items-start gap-2 px-3 py-3 text-sm wrap-break-word outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                >
                    <MapPin
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    /><span class="min-w-0">{{ suggestion }}</span>
                </ComboboxItem>
                <p
                    class="border-t border-border px-3 py-2 text-xs text-muted-foreground"
                >
                    {{ t('trips.place_search.credit') }}
                </p>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
    <p :id="`${id}-place-hint`" class="text-sm text-muted-foreground">
        {{ t('trips.place_search.hint') }}
    </p>
</template>
