<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import TripCard from '@/components/trips/TripCard.vue';
import TripForm from '@/components/trips/TripForm.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type { TripPage } from '@/types/trip';
const props = defineProps<{
    trips: TripPage;
    mine?: boolean;
    filters?: { transport?: string };
}>();
const { t } = useTranslations();
const creating = ref(false);
function filter(value: unknown) {
    router.get('/trips', value === 'all' ? {} : { transport: String(value) }, {
        preserveScroll: true,
        replace: true,
    });
}
</script>
<template>
    <main
        class="mx-auto w-full max-w-4xl min-w-0 space-y-6 px-4 pt-4 pb-28 sm:px-6"
    >
        <OutingsNavigation active="trips" />
        <header class="space-y-3">
            <h1 class="text-3xl font-semibold">
                {{ t(mine ? 'trips.mine' : 'trips.title') }}
            </h1>
            <p class="text-muted-foreground">{{ t('trips.description') }}</p>
            <Button data-test="trip-create" @click="creating = true">{{
                t('trips.create')
            }}</Button>
        </header>
        <nav class="flex gap-3">
            <Button as-child :variant="mine ? 'outline' : 'secondary'"
                ><Link href="/trips">{{ t('trips.discover') }}</Link></Button
            ><Button as-child :variant="mine ? 'secondary' : 'outline'"
                ><Link href="/trips/mine">{{ t('trips.mine') }}</Link></Button
            >
        </nav>
        <Select
            v-if="!mine"
            :model-value="props.filters?.transport ?? 'all'"
            @update:model-value="filter"
            ><SelectTrigger
                class="w-full"
                data-test="trip-transport-filter"
                :aria-label="t('trips.fields.transport')"
                ><SelectValue /></SelectTrigger
            ><SelectContent
                ><SelectItem value="all">{{ t('trips.all') }}</SelectItem
                ><SelectItem
                    v-for="kind in ['car', 'train', 'rer', 'bus']"
                    :key="kind"
                    :value="kind"
                    >{{ t(`trips.transport.${kind}`) }}</SelectItem
                ></SelectContent
            ></Select
        >
        <p v-if="!trips.data.length" class="py-8 text-muted-foreground">
            {{ t('trips.empty') }}
        </p>
        <div class="grid min-w-0 gap-4 sm:grid-cols-2">
            <TripCard v-for="trip in trips.data" :key="trip.id" :trip="trip" />
        </div>
        <nav class="flex flex-wrap gap-2">
            <template v-for="(link, i) in trips.links" :key="i"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    class="rounded-lg border border-border px-3 py-2"
                    ><span v-html="link.label" /></Link
            ></template>
        </nav>
        <ResponsivePanel
            v-model:open="creating"
            :title="t('trips.create')"
            :description="t('trips.description')"
            ><TripForm @saved="creating = false" /><Button
                class="mt-4 w-full"
                variant="outline"
                @click="creating = false"
                >{{ t('trips.close') }}</Button
            ></ResponsivePanel
        >
    </main>
</template>
