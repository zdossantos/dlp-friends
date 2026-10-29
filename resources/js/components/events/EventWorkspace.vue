<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, watch } from 'vue';
import WorkspaceNavigation from '@/components/common/WorkspaceNavigation.vue';
import EventCard from '@/components/events/EventCard.vue';
import EventWorkspacePanel from '@/components/events/EventWorkspacePanel.vue';
import InputError from '@/components/InputError.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventWorkspaceQuery } from '@/composables/useEventWorkspaceQuery';
import { useTranslations } from '@/composables/useTranslations';
import {
    eventWorkspaceOpener,
    forgetEventWorkspaceScroll,
    rememberEventWorkspaceScroll,
    restoreEventWorkspaceScroll,
} from '@/lib/eventWorkspaceScroll';
import { create, index, mine } from '@/routes/events';
import type { EventWorkspaceProps } from '@/types/event';

const props = defineProps<EventWorkspaceProps>();
const { t } = useTranslations();

if (props.panel) {
    restoreEventWorkspaceScroll();
}

const origin = useEventWorkspaceQuery(() => props.context);
const createHref = computed(() => create({ query: origin.value }).url);
const form = useForm({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    search: props.filters?.search ?? '',
});
const hasFilters = computed(() => Object.keys(props.filters ?? {}).length > 0);
watch(
    () => props.filters,
    (filters) => {
        form.from = filters?.from ?? '';
        form.to = filters?.to ?? '';
        form.search = filters?.search ?? '';
    },
);
function applyFilters(): void {
    form.get(props.context === 'mine' ? mine().url : index().url, {
        preserveScroll: true,
        preserveState: true,
    });
}
function clearFilters(): void {
    router.get(
        props.context === 'mine' ? mine().url : index().url,
        {},
        { preserveScroll: true },
    );
}
function rememberCreateOpener(): void {
    rememberEventWorkspaceScroll('event-create');
}

function updatePanel(open: boolean): void {
    if (!open) {
        router.visit(props.closeHref, {
            preserveScroll: true,
            onFinish: () => {
                const opener = eventWorkspaceOpener();

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
        class="mx-auto w-full max-w-5xl min-w-0 space-y-7 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-24 sm:px-6 sm:pt-8"
    >
        <OutingsNavigation active="events" />
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-full min-w-0">
                <h1
                    class="text-3xl font-semibold wrap-break-word text-foreground"
                >
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

        <WorkspaceNavigation
            :label="t('events.workspace_navigation')"
            :items="[
                {
                    href: index({ query: filters }).url,
                    label: t('events.actions.discover'),
                    active: context === 'discover',
                    testId: 'events-nav-discover',
                },
                {
                    href: mine({ query: filters }).url,
                    label: t('events.actions.mine'),
                    active: context === 'mine',
                    testId: 'events-nav-mine',
                },
            ]"
        />

        <form
            class="space-y-3 rounded-2xl border border-border p-4"
            data-test="event-filters"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-2 sm:col-span-2">
                    <Label for="event-search">{{
                        t('events.filters.search')
                    }}</Label>
                    <Input
                        id="event-search"
                        v-model="form.search"
                        type="search"
                        maxlength="160"
                        :placeholder="t('events.filters.search_placeholder')"
                        :aria-invalid="!!form.errors.search"
                        aria-describedby="event-search-error"
                    />
                    <InputError
                        id="event-search-error"
                        :message="form.errors.search"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="event-from">{{
                        t('events.filters.from')
                    }}</Label>
                    <Input
                        id="event-from"
                        v-model="form.from"
                        type="date"
                        :aria-invalid="!!form.errors.from"
                        aria-describedby="event-from-error event-date-help"
                    />
                    <InputError
                        id="event-from-error"
                        :message="form.errors.from"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="event-to">{{ t('events.filters.to') }}</Label>
                    <Input
                        id="event-to"
                        v-model="form.to"
                        type="date"
                        :aria-invalid="!!form.errors.to"
                        aria-describedby="event-to-error event-date-help"
                    />
                    <InputError id="event-to-error" :message="form.errors.to" />
                </div>
            </div>
            <p id="event-date-help" class="text-sm text-muted-foreground">
                {{ t('events.filters.date_help') }}
            </p>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="event-filter-apply"
                    >{{ t('events.filters.apply') }}</Button
                >
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="outline"
                    data-test="event-filter-clear"
                    @click="clearFilters"
                    >{{ t('events.filters.clear') }}</Button
                >
            </div>
            <p v-if="hasFilters" class="text-sm font-medium" role="status">
                {{ t('events.filters.active') }}
            </p>
        </form>

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
                    {{
                        t(
                            hasFilters
                                ? 'events.filters.empty_title'
                                : 'events.index.empty_title',
                        )
                    }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        t(
                            hasFilters
                                ? 'events.filters.empty'
                                : 'events.index.empty_description',
                        )
                    }}
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
                    {{
                        t(
                            hasFilters
                                ? 'events.filters.empty'
                                : 'events.mine.empty_organized',
                        )
                    }}
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
                    {{
                        t(
                            hasFilters
                                ? 'events.filters.empty'
                                : 'events.mine.empty_participating',
                        )
                    }}
                </p>
            </section>
        </template>
    </main>

    <EventWorkspacePanel
        :panel="panel"
        :context="context"
        :close-href="closeHref"
        @update:open="updatePanel"
    />
</template>
