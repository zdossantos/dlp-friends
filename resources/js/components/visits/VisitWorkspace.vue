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
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import UserAvatar from '@/components/profile/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VisitAgenda from '@/components/visits/VisitAgenda.vue';
import VisitCalendar from '@/components/visits/VisitCalendar.vue';
import VisitDayDetail from '@/components/visits/VisitDayDetail.vue';
import VisitForm from '@/components/visits/VisitForm.vue';
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
function navigate(patch: Partial<VisitFilters>) {
    cancelSearch?.();
    router.get(
        index.url(),
        { ...props.filters, ...patch },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
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
    timer = setTimeout(() => navigate({ search: value, member: null }), 300);
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
function edit(visit: OwnVisit) {
    editing.value = visit;
    formOpen.value = true;
}
function create() {
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
function selectMember(id: number) {
    if (props.panel) {
        router.get(
            index.url(),
            { ...props.filters, member: id },
            { preserveState: true, preserveScroll: true },
        );
    } else {
        navigate({ member: id });
    }
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
                    }}</Label
                    ><select
                        id="visit-audience"
                        :value="filters.audience"
                        class="mt-2 block min-h-11 w-full max-w-full rounded-xl border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                        @change="
                            navigate({
                                audience: ($event.target as HTMLSelectElement)
                                    .value as 'members' | 'matches',
                            })
                        "
                    >
                        <option value="members">
                            {{ t('visits.all_members') }}
                        </option>
                        <option value="matches">
                            {{ t('visits.my_matches') }}
                        </option>
                    </select>
                </div>
            </div>
            <Button
                v-if="filters.member"
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
                <div>
                    <Label for="visit-month" class="sr-only">{{
                        t('visits.month')
                    }}</Label
                    ><input
                        id="visit-month"
                        type="month"
                        :value="filters.month"
                        class="min-h-11 w-full max-w-48 min-w-0 rounded-xl border border-input bg-background px-2 text-center font-semibold focus-visible:ring-2 focus-visible:ring-ring"
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
        @update:open="formOpen = $event"
        ><VisitForm
            v-if="formOpen"
            :key="editing?.id ?? 'new'"
            :visit="editing"
            :day="filters.day"
            @saved="formOpen = false"
        /><Button
            variant="outline"
            class="mt-4 min-h-11 w-full"
            @click="formOpen = false"
            >{{ t('visits.close') }}</Button
        ></ResponsivePanel
    >
    <ResponsivePanel
        :open="!!panel"
        :title="
            t('visits.people_title', { count: panel?.members.meta.total ?? 0 })
        "
        :description="
            panel
                ? formatDate(panel.date + 'T12:00:00', { dateStyle: 'full' })
                : ''
        "
        test-id="visit-attendees-panel"
        fill-height
        @close-auto-focus="
            $event.preventDefault();
            restore();
        "
        @update:open="!$event && closePanel()"
    >
        <template v-if="panel"
            ><Button
                class="mb-4 min-h-11"
                variant="outline"
                data-test="visit-attendees-back"
                @click="closePanel"
                >{{ t('visits.back') }}</Button
            >
            <ul class="divide-y divide-border">
                <li v-for="member in panel.members.data" :key="member.id">
                    <button
                        type="button"
                        class="flex min-h-14 w-full items-center gap-3 rounded-lg py-2 text-left focus-visible:ring-2 focus-visible:ring-ring"
                        @click="selectMember(member.id)"
                    >
                        <UserAvatar
                            :avatar="member.avatar"
                            :display-name="member.display_name"
                        /><span class="min-w-0 flex-1 truncate">{{
                            member.display_name
                        }}</span
                        ><span
                            v-if="member.is_match"
                            class="text-xs text-muted-foreground"
                            >{{ t('visits.match') }}</span
                        >
                    </button>
                </li>
            </ul>
            <nav
                :aria-label="t('visits.pagination')"
                class="mt-4 flex items-center justify-between gap-2"
            >
                <Button
                    v-if="panel.members.links.prev"
                    as-child
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
