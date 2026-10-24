<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import BackLink from '@/components/common/BackLink.vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import EmbeddedMemberProfile from '@/components/members/EmbeddedMemberProfile.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import VisitAgenda from '@/components/visits/VisitAgenda.vue';
import VisitCalendar from '@/components/visits/VisitCalendar.vue';
import VisitDayDetail from '@/components/visits/VisitDayDetail.vue';
import VisitForm from '@/components/visits/VisitForm.vue';
import VisitMemberButton from '@/components/visits/VisitMemberButton.vue';
import VisitVisibilityControl from '@/components/visits/VisitVisibilityControl.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/visits';
import attendees from '@/routes/visits/attendees';
import type {
    OwnVisit,
    VisitFilters,
    VisitWorkspaceProps,
} from '@/types/visit';
const props = defineProps<VisitWorkspaceProps>();
const { t, formatDate } = useTranslations();
const editing = ref<OwnVisit | null>(null);
const formOpen = ref(false);
const search = ref(props.filters.search);
const busy = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let cancelSearch: (() => void) | undefined;
watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);
function navigate(patch: Partial<VisitFilters>, replace = false) {
    cancelSearch?.();
    router.get(
        index.url(),
        { ...props.filters, ...patch },
        {
            preserveState: true,
            preserveScroll: true,
            replace,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
            onCancelToken: (token) => {
                cancelSearch = () => token.cancel();
            },
        },
    );
}
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(
        () => navigate({ search: value, member: null }, true),
        300,
    );
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    cancelSearch?.();
});
const selected = computed(() =>
    props.days.find((day) => day.date === props.filters.day),
);
function month(delta: number) {
    const date = new Date(props.filters.month + '-01T12:00:00');
    date.setMonth(date.getMonth() + delta);
    const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    navigate({ month: value, day: value + '-01' });
}
let formOpener: string | undefined;
function rememberFormOpener() {
    formOpener = (document.activeElement as HTMLElement | null)?.dataset.test;
}
function restoreFormFocus(event: Event) {
    event.preventDefault();
    nextTick(() =>
        requestAnimationFrame(() => {
            const opener = formOpener
                ? document.querySelector<HTMLElement>(
                      `[data-test="${formOpener}"]`,
                  )
                : null;
            (
                opener ??
                document.querySelector<HTMLElement>(
                    '[data-test="visit-create"]',
                )
            )?.focus({ preventScroll: true });
        }),
    );
}
function edit(visit: OwnVisit) {
    rememberFormOpener();
    editing.value = visit;
    formOpen.value = true;
}
function create() {
    rememberFormOpener();
    editing.value = null;
    formOpen.value = true;
}
function all(date: string, event: Event) {
    const element = event.currentTarget as HTMLElement;
    element.id = `visit-opener-${date}`;
    sessionStorage.setItem(
        'visit-scroll',
        JSON.stringify({
            y: window.scrollY,
            id: element.id,
            href: window.location.pathname,
            month: props.filters.month,
        }),
    );
    router.get(
        attendees.index.url(),
        { ...props.filters, day: date },
        { preserveState: true, preserveScroll: true },
    );
}
function restore() {
    const saved = sessionStorage.getItem('visit-scroll');

    if (!saved) {
        return;
    }

    const { y, id, href, month } = JSON.parse(saved) as {
        y: number;
        id: string;
        href: string;
        month: string;
    };

    if (href !== window.location.pathname || month !== props.filters.month) {
        return;
    }

    nextTick(() =>
        requestAnimationFrame(() => {
            window.scrollTo(0, y);
            document.getElementById(id)?.focus({ preventScroll: true });
        }),
    );
}
onMounted(() => {
    if (!props.panel) {
        restore();
    }
});
// Inertia schedules its history scroll restoration before emitting navigate.
const removeNavigateListener = router.on('navigate', () => {
    if (!props.panel) {
        restore();
    }
});
onBeforeUnmount(removeNavigateListener);
watch(
    () => props.panel,
    (panel, previous) => {
        if (previous && !panel) {
            restore();
        }
    },
);
function closePanel() {
    router.visit(props.closeHref, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: restore,
    });
}
function selectMember(id: number, date: string) {
    const url = new URL(window.location.href);
    url.searchParams.set('profile', String(id));
    url.searchParams.set('profile_day', date);
    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, preserveScroll: true },
    );
}
function closeProfile() {
    router.visit(props.profileCloseHref, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>
<template>
    <Head :title="t('visits.title')" />
    <main
        class="mx-auto w-full max-w-3xl min-w-0 space-y-6 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-28 sm:px-6 sm:pt-8"
        :aria-busy="busy"
    >
        <OutingsNavigation active="visits" />
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-semibold">{{ t('visits.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('visits.description') }}
                </p>
            </div>
            <Button data-test="visit-create" class="min-h-11" @click="create"
                ><Plus class="size-4" aria-hidden="true" />{{
                    t('visits.add')
                }}</Button
            >
        </header>
        <VisitVisibilityControl :visibility="visibility" />
        <section class="space-y-4" :aria-label="t('visits.filters')">
            <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <div class="min-w-0 flex-1">
                    <Label for="visit-search">{{ t('visits.search') }}</Label
                    ><Input
                        id="visit-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        class="mt-2 min-h-11"
                        :placeholder="t('visits.search_placeholder')"
                    />
                </div>
                <div>
                    <Label for="visit-audience">{{
                        t('visits.audience')
                    }}</Label>
                    <Select
                        :model-value="filters.audience"
                        @update:model-value="
                            navigate({
                                audience: $event as 'members' | 'matches',
                            })
                        "
                    >
                        <SelectTrigger
                            id="visit-audience"
                            class="mt-2 min-h-11 w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                class="min-h-11"
                                value="matches"
                                data-test="visit-audience-matches"
                                >{{ t('visits.my_matches') }}</SelectItem
                            >
                            <SelectItem
                                class="min-h-11"
                                value="members"
                                data-test="visit-audience-members"
                                >{{ t('visits.all_members') }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <Button
                v-if="filters.member"
                class="min-h-11"
                variant="outline"
                @click="navigate({ member: null })"
                >{{ t('visits.clear_member') }}</Button
            >
            <div class="flex items-center justify-between gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    data-test="visit-previous-month"
                    :aria-label="t('visits.previous_month')"
                    class="size-11"
                    @click="month(-1)"
                    ><ChevronLeft aria-hidden="true"
                /></Button>
                <div class="min-w-0">
                    <Label for="visit-month" class="sr-only">{{
                        t('visits.month')
                    }}</Label
                    ><input
                        id="visit-month"
                        type="month"
                        :value="filters.month"
                        class="block h-11 w-full max-w-48 min-w-0 appearance-none rounded-xl border border-input bg-background px-2 text-center leading-[2.625rem] font-semibold focus-visible:ring-2 focus-visible:ring-ring"
                        @change="
                            navigate({
                                month: ($event.target as HTMLInputElement)
                                    .value,
                                day:
                                    ($event.target as HTMLInputElement).value +
                                    '-01',
                            })
                        "
                    />
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-11"
                    data-test="visit-next-month"
                    :aria-label="t('visits.next_month')"
                    @click="month(1)"
                    ><ChevronRight aria-hidden="true"
                /></Button>
            </div>
            <div class="grid grid-cols-2 rounded-xl bg-muted p-1">
                <button
                    v-for="view in ['agenda', 'calendar'] as const"
                    :key="view"
                    type="button"
                    :aria-pressed="filters.view === view"
                    :data-test="`visit-view-${view}`"
                    class="min-h-11 rounded-lg px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        filters.view === view
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="navigate({ view })"
                >
                    {{ t(`visits.${view}`) }}
                </button>
            </div>
        </section>
        <VisitAgenda
            v-if="filters.view === 'agenda'"
            :days="days"
            :own-visits="ownVisits"
            :visibility="visibility"
            :filtered="
                !!filters.search ||
                filters.audience !== 'matches' ||
                !!filters.member
            "
            @clear-filters="
                navigate({ search: '', audience: 'matches', member: null })
            "
            @edit="edit"
            @attendees="all"
            @member="selectMember"
        />
        <template v-else
            ><VisitCalendar
                :month="filters.month"
                :days="days"
                :selected="filters.day"
                @select="navigate({ day: $event })" />
            <div v-if="selected" class="space-y-4 border-t border-border pt-5">
                <h2 class="text-lg font-semibold">
                    {{
                        formatDate(selected.date + 'T12:00:00', {
                            dateStyle: 'full',
                        })
                    }}
                </h2>
                <VisitDayDetail
                    :day="selected"
                    :own-visits="ownVisits"
                    :visibility="visibility"
                    @edit="edit"
                    @attendees="all"
                    @member="selectMember"
                /></div
        ></template>
    </main>
    <ResponsivePanel
        :open="formOpen"
        :title="t(editing ? 'visits.edit_title' : 'visits.add')"
        :description="t('visits.form_description')"
        test-id="visit-form-panel"
        @close-auto-focus="restoreFormFocus"
        @update:open="formOpen = $event"
        ><VisitForm
            v-if="formOpen"
            :key="editing?.id ?? 'new'"
            :visit="editing"
            :day="today"
            @saved="formOpen = false"
        /><Button
            variant="outline"
            class="mt-4 min-h-11 w-full"
            @click="formOpen = false"
            >{{ t('visits.close') }}</Button
        ></ResponsivePanel
    >
    <ResponsivePanel
        :open="!!panel || !!memberProfile"
        :title="
            memberProfile
                ? ''
                : t('visits.people_title', {
                      count: panel?.members.meta.total ?? 0,
                  })
        "
        :description="
            panel && !memberProfile
                ? formatDate(panel.date + 'T12:00:00', { dateStyle: 'full' })
                : ''
        "
        :test-id="
            memberProfile ? 'visit-profile-panel' : 'visit-attendees-panel'
        "
        :full-bleed="!!memberProfile"
        :a11y-heading="t('events.panels.participant-profile.title')"
        :a11y-summary="t('events.panels.participant-profile.description')"
        :back-href="memberProfile ? profileCloseHref : null"
        :back-label="t('visits.back')"
        back-data-test="visit-profile-back"
        preserve-state
        fill-height
        @close-auto-focus="
            $event.preventDefault();
            restore();
        "
        @update:open="
            !$event && (memberProfile ? closeProfile() : closePanel())
        "
    >
        <EmbeddedMemberProfile
            v-if="memberProfile"
            :profile="memberProfile"
            :block-return-href="profileCloseHref"
        />
        <template v-else-if="panel"
            ><Button
                class="mb-4 min-h-11"
                variant="outline"
                data-test="visit-attendees-back"
                as-child
                ><BackLink :href="closeHref" preserve-state>{{
                    t('visits.back')
                }}</BackLink></Button
            >
            <ul class="divide-y divide-border">
                <li v-for="member in panel.members.data" :key="member.id">
                    <VisitMemberButton
                        :member="member"
                        :data-test="`visit-attendee-${member.id}`"
                        @select="selectMember($event, panel.date)"
                    />
                </li>
            </ul>
            <nav
                :aria-label="t('visits.pagination')"
                class="mt-4 flex items-center justify-between gap-2"
            >
                <Button
                    v-if="panel.members.links.prev"
                    as-child
                    class="min-h-11"
                    variant="outline"
                    ><Link
                        :href="panel.members.links.prev"
                        preserve-scroll
                        preserve-state
                        >{{ t('visits.previous') }}</Link
                    ></Button
                ><span class="text-sm">{{
                    t('visits.page', {
                        page: panel.members.meta.current_page,
                        total: panel.members.meta.last_page,
                    })
                }}</span
                ><Button
                    v-if="panel.members.links.next"
                    as-child
                    class="min-h-11"
                    variant="outline"
                    ><Link
                        :href="panel.members.links.next"
                        preserve-scroll
                        preserve-state
                        >{{ t('visits.next') }}</Link
                    ></Button
                >
            </nav></template
        >
    </ResponsivePanel>
</template>
