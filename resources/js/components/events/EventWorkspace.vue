<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick } from 'vue';
import AdaptiveEventPanel from '@/components/events/AdaptiveEventPanel.vue';
import EventCard from '@/components/events/EventCard.vue';
import EventPanelContent from '@/components/events/EventPanelContent.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { create, index, mine } from '@/routes/events';
import type { EventWorkspaceProps } from '@/types/event';

const props = defineProps<EventWorkspaceProps>();
const { t } = useTranslations();

const panelTitle = computed(() => {
    if (!props.panel) {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.title`);
});
const panelDescription = computed(() => {
    if (!props.panel) {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.description`);
});
const createHref = computed(
    () =>
        create(props.context === 'mine' ? { query: { origin: 'mine' } } : {})
            .url,
);

function rememberCreateOpener(): void {
    sessionStorage.setItem('event-panel-opener', 'event-create');
}

function updatePanel(open: boolean): void {
    if (!open) {
        router.visit(props.closeHref, {
            preserveScroll: true,
            onSuccess: () => {
                const opener = sessionStorage.getItem('event-panel-opener');
                if (!opener) return;
                nextTick(() => {
                    document
                        .querySelector<HTMLElement>(`[data-test="${opener}"]`)
                        ?.focus({ preventScroll: true });
                    sessionStorage.removeItem('event-panel-opener');
                });
            },
        });
    }
}
</script>

<template>
    <main
        :data-test="context === 'discover' ? 'discover-events' : 'mine-events'"
        class="mx-auto w-full max-w-5xl space-y-7 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-24 sm:px-6 sm:pt-8"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">
                    {{
                        t(
                            context === 'discover'
                                ? 'events.index.title'
                                : 'events.mine.title',
                        )
                    }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{
                        t(
                            context === 'discover'
                                ? 'events.index.description'
                                : 'events.mine.description',
                        )
                    }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="context === 'discover' ? mine() : index()">
                        {{
                            t(
                                context === 'discover'
                                    ? 'events.actions.mine'
                                    : 'events.navigation',
                            )
                        }}
                    </Link>
                </Button>
                <Button as-child>
                    <Link
                        :href="createHref"
                        preserve-scroll
                        data-test="event-create"
                        @click="rememberCreateOpener"
                        >{{ t('events.actions.create') }}</Link
                    >
                </Button>
            </div>
        </header>

        <template v-if="context === 'discover'">
            <section v-if="events?.length" class="grid gap-4 md:grid-cols-2">
                <EventCard
                    v-for="event in events"
                    :key="event.id"
                    :event="event"
                    context="discover"
                />
            </section>
            <section
                v-else
                class="rounded-3xl border border-dashed border-border p-10 text-center"
            >
                <h2 class="font-semibold">
                    {{ t('events.index.empty_title') }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('events.index.empty_description') }}
                </p>
            </section>
        </template>

        <template v-else>
            <section data-test="organized-events" class="space-y-3">
                <h2 class="text-xl font-semibold">
                    {{ t('events.mine.organized') }}
                </h2>
                <div v-if="organized?.length" class="grid gap-4 md:grid-cols-2">
                    <EventCard
                        v-for="event in organized"
                        :key="event.id"
                        :event="event"
                        context="mine"
                        role="organizer"
                    />
                </div>
                <p
                    v-else
                    class="rounded-2xl border border-dashed border-border p-6 text-muted-foreground"
                >
                    {{ t('events.mine.empty_organized') }}
                </p>
            </section>
            <section data-test="participating-events" class="space-y-3">
                <h2 class="text-xl font-semibold">
                    {{ t('events.mine.participating') }}
                </h2>
                <div
                    v-if="participating?.length"
                    class="grid gap-4 md:grid-cols-2"
                >
                    <EventCard
                        v-for="event in participating"
                        :key="event.id"
                        :event="event"
                        context="mine"
                        role="participant"
                    />
                </div>
                <p
                    v-else
                    class="rounded-2xl border border-dashed border-border p-6 text-muted-foreground"
                >
                    {{ t('events.mine.empty_participating') }}
                </p>
            </section>
        </template>
    </main>

    <AdaptiveEventPanel
        :open="panel !== null"
        :title="panelTitle"
        :description="panelDescription"
        @update:open="updatePanel"
    >
        <EventPanelContent v-if="panel" :panel="panel" :context="context" />
    </AdaptiveEventPanel>
</template>
