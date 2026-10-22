<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import BackLink from '@/components/common/BackLink.vue';
import ResponsivePanel from '@/components/common/ResponsivePanel.vue';
import EventParticipantProfile from '@/components/events/EventParticipantProfile.vue';
import OutingsNavigation from '@/components/outings/OutingsNavigation.vue';
import TripDepartureReportForm from '@/components/trips/TripDepartureReportForm.vue';
import TripForm from '@/components/trips/TripForm.vue';
import TripInformation from '@/components/trips/TripInformation.vue';
import TripParticipantList from '@/components/trips/TripParticipantList.vue';
import TripRegistrationActions from '@/components/trips/TripRegistrationActions.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { goBack } from '@/lib/navigationHistory';
import type { EmbeddedMemberProfile } from '@/types/event';
import type {
    TripDetail,
    TripMember,
    TripRegistration,
    TripDeparture,
} from '@/types/trip';
const props = defineProps<{
    trip: TripDetail;
    panel?: string;
    profile?: EmbeddedMemberProfile;
    participants?: { id: number | null; member: TripMember }[];
    registrations?: TripRegistration[];
    departures?: TripDeparture[];
}>();
const { t, formatDate } = useTranslations();
const editing = ref(false);
function close(open: boolean) {
    if (!open) {
        goBack(`/trips/${props.trip.id}`, { preserveScroll: true });
    }
}
</script>
<template>
    <Head :title="t('trips.details')" />
    <main
        class="mx-auto w-full max-w-3xl min-w-0 space-y-6 px-4 pt-4 pb-28 sm:px-6"
    >
        <OutingsNavigation active="trips" />
        <Button as-child variant="outline"
            ><BackLink href="/trips">{{ t('trips.back') }}</BackLink></Button
        >
        <TripInformation :trip="trip" />
        <div class="flex flex-wrap gap-3">
            <Button
                v-if="trip.can_edit"
                variant="outline"
                data-test="trip-edit"
                @click="editing = true"
                >{{ t('trips.edit') }}</Button
            ><Button
                v-if="trip.meeting_point !== undefined"
                as-child
                variant="outline"
                ><Link
                    :href="`/trips/${trip.id}/participants`"
                    :data-test="panel ? undefined : 'trip-participants'"
                    preserve-scroll
                    >{{ t('trips.participants') }}</Link
                ></Button
            ><Button v-if="trip.can_manage" as-child variant="outline"
                ><Link
                    :href="`/trips/${trip.id}/requests`"
                    data-test="trip-requests"
                    preserve-scroll
                    >{{ t('trips.requests') }}</Link
                ></Button
            >
        </div>
        <Button v-if="trip.chat_href" as-child
            ><Link :href="trip.chat_href" data-test="trip-chat">{{
                t('trips.chat.open')
            }}</Link></Button
        >
        <TripRegistrationActions :trip="trip" />
        <ResponsivePanel
            v-model:open="editing"
            :title="t('trips.edit')"
            :description="t('trips.description')"
            ><TripForm :key="trip.id" :trip="trip" @saved="editing = false"
        /></ResponsivePanel>
        <ResponsivePanel
            :open="Boolean(panel)"
            :title="
                t(
                    panel === 'profile'
                        ? 'trips.details'
                        : panel === 'requests'
                          ? 'trips.requests'
                          : 'trips.participants',
                )
            "
            :description="t('trips.description')"
            :back-href="panel === 'profile' ? '/trips' : `/trips/${trip.id}`"
            :back-label="t('trips.back')"
            :back-data-test="
                panel === 'profile' ? 'profile-close' : 'trip-panel-close'
            "
            back-in-header
            @update:open="close"
        >
            <nav v-if="panel !== 'profile'" class="mb-4 flex flex-wrap gap-2">
                <Button as-child variant="outline"
                    ><Link
                        :href="`/trips/${trip.id}/participants`"
                        data-test="trip-participants"
                        replace
                        preserve-scroll
                        >{{ t('trips.participants') }}</Link
                    ></Button
                >
                <Button v-if="trip.can_manage" as-child variant="outline"
                    ><Link
                        :href="`/trips/${trip.id}/requests`"
                        replace
                        preserve-scroll
                        >{{ t('trips.requests') }}</Link
                    ></Button
                >
            </nav>
            <EventParticipantProfile
                v-if="profile"
                :profile="profile"
                :block-return-href="`/trips/${trip.id}`"
            />
            <TripParticipantList
                v-if="participants"
                :trip="trip"
                :participants="participants"
            />
            <ul v-if="registrations" class="divide-y divide-border">
                <li
                    v-for="registration in registrations"
                    :key="registration.id"
                    class="space-y-3 py-4"
                >
                    <Link
                        v-if="
                            registration.member &&
                            ['pending', 'accepted'].includes(
                                registration.status,
                            )
                        "
                        :href="`/trips/${trip.id}/members/${registration.member.id}`"
                        class="inline-flex min-h-11 items-center text-primary underline"
                        preserve-scroll
                        >{{ registration.member.display_name }}</Link
                    >
                    <p v-else>
                        {{
                            registration.member?.display_name ??
                            t('trips.deleted_member')
                        }}
                    </p>
                    <p>{{ t(`trips.status.${registration.status}`) }}</p>
                    <p v-if="registration.reason" class="whitespace-pre-wrap">
                        {{ registration.reason }}
                    </p>
                    <TripRegistrationActions
                        :trip="trip"
                        :registration="registration"
                    />
                </li>
            </ul>
            <section v-if="departures?.length" class="mt-6 space-y-4">
                <h2 class="text-xl font-semibold">
                    {{ t('trips.departures') }}
                </h2>
                <article
                    v-for="departure in departures"
                    :key="departure.id"
                    class="space-y-3 rounded-xl border border-border p-4"
                >
                    <h3 class="font-medium">
                        {{
                            departure.display_name ?? t('trips.deleted_member')
                        }}
                    </h3>
                    <p>
                        {{
                            formatDate(departure.left_at, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                                timeZone: 'Europe/Paris',
                            })
                        }}
                    </p>
                    <p class="whitespace-pre-wrap">{{ departure.reason }}</p>
                    <TripDepartureReportForm :departure="departure" />
                </article>
            </section>
        </ResponsivePanel>
    </main>
</template>
