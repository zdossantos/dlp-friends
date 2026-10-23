<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Search, ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref, watch } from 'vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import TripCard from '@/components/trips/TripCard.vue';
import TripForm from '@/components/trips/TripForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    filters?: { transport?: string; search?: string };
}>();
const { t } = useTranslations();
const creating = ref(false);
const search = ref(props.filters?.search ?? '');
watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);
function filter(value: unknown = props.filters?.transport ?? 'all') {
    router.get(
        '/trips',
        {
            ...(value !== 'all' ? { transport: String(value) } : {}),
            ...(search.value.trim() ? { search: search.value.trim() } : {}),
        },
        { preserveScroll: true },
    );
}
function clearSearch() {
    search.value = '';
    filter();
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
        <form v-if="!mine" class="space-y-3" @submit.prevent="filter()">
            <Label for="trip-search">{{ t('trips.search') }}</Label>
            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="trip-search"
                        v-model="search"
                        type="search"
                        maxlength="160"
                        :placeholder="t('trips.search_placeholder')"
                        class="pl-10"
                    />
                </div>
                <Button type="submit" data-test="trip-search-submit">{{
                    t('trips.search_submit')
                }}</Button>
            </div>
            <Select
                :model-value="props.filters?.transport ?? 'all'"
                @update:model-value="filter"
            >
                <SelectTrigger
                    class="w-full"
                    data-test="trip-transport-filter"
                    :aria-label="t('trips.fields.transport')"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ t('trips.all') }}</SelectItem>
                    <SelectItem
                        v-for="kind in ['car', 'train', 'rer', 'bus']"
                        :key="kind"
                        :value="kind"
                        >{{ t(`trips.transport.${kind}`) }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button
                v-if="props.filters?.search"
                type="button"
                variant="ghost"
                @click="clearSearch"
                >{{ t('trips.search_clear') }}</Button
            >
        </form>
        <p v-if="!trips.data.length" class="py-8 text-muted-foreground">
            {{ t('trips.empty') }}
        </p>
        <div class="grid min-w-0 gap-4 sm:grid-cols-2">
            <TripCard v-for="trip in trips.data" :key="trip.id" :trip="trip" />
        </div>
        <nav
            v-if="trips.last_page > 1"
            data-test="trip-pagination"
            :aria-label="t('trips.pagination')"
            class="flex items-center justify-between gap-2 border-t border-border pt-4"
        >
            <Button v-if="trips.prev_page_url" as-child variant="outline"
                ><Link :href="trips.prev_page_url" rel="prev"
                    ><ChevronLeft class="size-4" aria-hidden="true" />{{
                        t('trips.previous')
                    }}</Link
                ></Button
            >
            <Button v-else disabled variant="outline"
                ><ChevronLeft class="size-4" aria-hidden="true" />{{
                    t('trips.previous')
                }}</Button
            >
            <span
                class="text-center text-sm text-muted-foreground"
                aria-live="polite"
                >{{
                    t('trips.page', {
                        current: trips.current_page,
                        last: trips.last_page,
                    })
                }}</span
            >
            <Button v-if="trips.next_page_url" as-child variant="outline"
                ><Link :href="trips.next_page_url" rel="next"
                    >{{ t('trips.next')
                    }}<ChevronRight class="size-4" aria-hidden="true" /></Link
            ></Button>
            <Button v-else disabled variant="outline"
                >{{ t('trips.next')
                }}<ChevronRight class="size-4" aria-hidden="true"
            /></Button>
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
