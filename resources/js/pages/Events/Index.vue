<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventCard from '@/components/events/EventCard.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { create, mine } from '@/routes/events';
import type { EventSummary } from '@/types/event';

defineProps<{ events: EventSummary[] }>();
const { t } = useTranslations();
</script>

<template>
    <Head :title="t('events.index.title')" />
    <main
        class="mx-auto w-full max-w-5xl space-y-6 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-24 sm:px-6 sm:pt-8"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold">
                    {{ t('events.index.title') }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{ t('events.index.description') }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="outline"
                    ><Link :href="mine()">{{
                        t('events.actions.mine')
                    }}</Link></Button
                ><Button as-child
                    ><Link :href="create()">{{
                        t('events.actions.create')
                    }}</Link></Button
                >
            </div>
        </header>
        <section v-if="events.length" class="grid gap-4 md:grid-cols-2">
            <EventCard v-for="event in events" :key="event.id" :event="event" />
        </section>
        <section
            v-else
            class="rounded-3xl border border-dashed p-10 text-center"
        >
            <h2 class="font-semibold">{{ t('events.index.empty_title') }}</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('events.index.empty_description') }}
            </p>
        </section>
    </main>
</template>
