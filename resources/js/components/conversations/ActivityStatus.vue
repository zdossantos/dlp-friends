<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    presence?: { online: boolean; last_active_at: string | null } | null;
    typing?: boolean;
}>();
const { t } = useTranslations();
const page = usePage();
const clock = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(
    () => (timer = setInterval(() => (clock.value = Date.now()), 30_000)),
);
onBeforeUnmount(() => timer && clearInterval(timer));

const label = computed(() => {
    if (props.typing) return t('conversations.presence.typing');
    if (props.presence?.online) return t('conversations.presence.online');
    if (!props.presence?.last_active_at) return null;
    const seconds = Math.max(
        0,
        Math.round(
            (clock.value - Date.parse(props.presence.last_active_at)) / 1000,
        ),
    );
    const formatter = new Intl.RelativeTimeFormat(page.props.i18n.locale, {
        numeric: 'auto',
    });
    const relative =
        seconds < 3_600
            ? formatter.format(-Math.max(1, Math.round(seconds / 60)), 'minute')
            : seconds < 86_400
              ? formatter.format(-Math.round(seconds / 3_600), 'hour')
              : formatter.format(-Math.round(seconds / 86_400), 'day');
    return t('conversations.presence.last_active', { time: relative });
});
</script>

<template>
    <span
        v-if="label"
        class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
    >
        <span
            v-if="presence?.online && !typing"
            class="size-2 rounded-full bg-emerald-500"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
