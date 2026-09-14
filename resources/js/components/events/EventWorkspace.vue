<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick } from 'vue';
import AdaptiveEventPanel from '@/components/events/AdaptiveEventPanel.vue';
import EventCard from '@/components/events/EventCard.vue';
import EventPanelContent from '@/components/events/EventPanelContent.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    forgetEventWorkspaceScroll,
    rememberEventWorkspaceScroll,
    restoreEventWorkspaceScroll,
} from '@/lib/eventWorkspaceScroll';
import { create, index, mine, show } from '@/routes/events';
import { index as participantsIndex } from '@/routes/events/participants';
import type { EventWorkspaceProps } from '@/types/event';

const props = defineProps<EventWorkspaceProps>();
const { t } = useTranslations();

if (props.panel) {
    restoreEventWorkspaceScroll();
}

const panelTitle = computed(() => {
    if (!props.panel || props.panel.kind === 'participant-profile') {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.title`);
});
const panelDescription = computed(() => {
    if (!props.panel || props.panel.kind === 'participant-profile') {
        return '';
    }

    return t(`events.panels.${props.panel.kind}.description`);
});
const createHref = computed(
    () =>
        create(props.context === 'mine' ? { query: { origin: 'mine' } } : {})
            .url,
);
const panelBackHref = computed(() => {
    if (props.panel?.kind === 'participant-profile') {
        return participantsIndex(props.panel.event.id, {
            query: props.context === 'mine' ? { origin: 'mine' } : {},
        }).url;
    }

    if (props.panel?.kind === 'registrations') {
        return show(props.panel.event.id, {
            query: props.context === 'mine' ? { origin: 'mine' } : {},
        }).url;
    }

    return null;
});
const panelBackDataTest = computed(() => {
    if (props.panel?.kind === 'participant-profile') {
        return 'participant-profile-back';
    }

    if (props.panel?.kind === 'registrations') {
        return 'event-registrations-back';
    }

    return undefined;
});
const accessiblePanelTitle = computed(() => {
    if (!props.panel) {
        return null;
    }

    return t(`events.panels.${props.panel.kind}.title`);
});
const accessiblePanelDescription = computed(() => {
    if (!props.panel) {
        return null;
    }

    return t(`events.panels.${props.panel.kind}.description`);
});

function rememberCreateOpener(event: Event): void {
    rememberEventWorkspaceScroll(event.currentTarget as HTMLElement | null);
    sessionStorage.setItem('event-panel-opener', 'event-create');
}

function updatePanel(open: boolean): void {
    if (!open) {
        router.visit(props.closeHref, {
            preserveScroll: true,
            onFinish: () => {
                const opener = sessionStorage.getItem('event-panel-opener');

                if (!opener) {
                    return;
                }

                nextTick(() => {
                    requestAnimationFrame(() => {
                        restoreEventWorkspaceScroll();
                        document
                            .querySelector<HTMLElement>(
                                `[data-test="${opener}"]`,
                            )
                            ?.focus({ preventScroll: true });

                        requestAnimationFrame(() => {
                            restoreEventWorkspaceScroll();
                            forgetEventWorkspaceScroll();
                            sessionStorage.removeItem('event-panel-opener');
                        });
                    });
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
                <Button as-child>
                    <Link
                        :href="createHref"
                        preserve-scroll
                        data-test="event-create"
                        @click="rememberCreateOpener"
                        @pointerdown.capture="rememberCreateOpener"
                        @keydown.enter.capture="rememberCreateOpener"
                        >{{ t('events.actions.create') }}</Link
                    >
                </Button>
            </div>
        </header>

        <nav
            class="grid grid-cols-2 rounded-xl bg-muted p-1"
            :aria-label="t('events.workspace_navigation')"
        >
            <Link
                :href="index()"
                data-test="events-nav-discover"
                :aria-current="context === 'discover' ? 'page' : undefined"
                class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    context === 'discover'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ t('events.actions.discover') }}
            </Link>
            <Link
                :href="mine()"
                data-test="events-nav-mine"
                :aria-current="context === 'mine' ? 'page' : undefined"
                class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    context === 'mine'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ t('events.actions.mine') }}
            </Link>
        </nav>

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
                <div>
                    <h2 class="text-xl font-semibold">
                        {{ t('events.mine.organized') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('events.mine.organized_description') }}
                    </p>
                </div>
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
                <div>
                    <h2 class="text-xl font-semibold">
                        {{ t('events.mine.participating') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('events.mine.participating_description') }}
                    </p>
                </div>
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
        :a11y-heading="accessiblePanelTitle"
        :a11y-summary="accessiblePanelDescription"
        :back-href="panelBackHref"
        :back-label="t('events.actions.back')"
        :back-data-test="panelBackDataTest"
        :full-bleed="
            panel?.kind === 'participant-profile' || panel?.kind === 'chat'
        "
        @update:open="updatePanel"
    >
        <EventPanelContent
            v-if="panel"
            :panel="panel"
            :context="context"
            :close-href="closeHref"
        />
    </AdaptiveEventPanel>
</template>
