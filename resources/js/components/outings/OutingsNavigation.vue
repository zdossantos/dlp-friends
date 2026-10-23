<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Footprints, Car } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';
import { index as events } from '@/routes/events';
import { index as visits } from '@/routes/visits';
defineProps<{ active: 'events' | 'visits' | 'trips' }>();
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
                { key: 'trips', href: '/trips', icon: Car },
            ]"
            :key="item.key"
            :href="item.href"
            :aria-current="active === item.key ? 'page' : undefined"
            :data-test="`outings-${item.key}`"
            class="flex min-h-11 min-w-0 flex-1 items-center justify-center gap-1 border-b-2 px-1 py-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring sm:gap-2 sm:px-3 sm:text-base"
            :class="
                active === item.key
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground'
            "
        >
            <component
                :is="item.icon"
                class="size-4 shrink-0 sm:size-5"
                aria-hidden="true"
            />{{ t(`outings.${item.key}`) }}
        </Link>
    </nav>
</template>
