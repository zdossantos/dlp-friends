<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Footprints } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import { index as events } from '@/routes/events';
import { index as visits } from '@/routes/visits';
defineProps<{ active: 'events' | 'visits' }>();
const { t } = useTranslations();
</script>
<template>
    <nav
        :aria-label="t('outings.navigation')"
        class="flex gap-1 border-b border-border"
    >
        <Link
            v-for="item in [
                { key: 'events', href: events(), icon: CalendarDays },
                { key: 'visits', href: visits(), icon: Footprints },
            ]"
            :key="item.key"
            :href="item.href"
            :aria-current="active === item.key ? 'page' : undefined"
            :data-test="`outings-${item.key}`"
            class="flex min-h-11 flex-1 items-center justify-center gap-2 border-b-2 px-3 py-3 font-semibold focus-visible:ring-2 focus-visible:ring-ring"
            :class="
                active === item.key
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground'
            "
        >
            <component :is="item.icon" class="size-5" aria-hidden="true" />{{
                t(`outings.${item.key}`)
            }}
        </Link>
    </nav>
</template>
