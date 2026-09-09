import type { AvatarOption } from './auth';
import type { PublicMember } from './member';

export type EventRegistrationStatus =
    'pending' | 'accepted' | 'refused' | 'withdrawn' | 'removed' | 'blocked';

export type EventSummary = {
    id: number;
    title: string;
    description: string;
    generalLocation: string;
    startsAt: string;
    capacity: number;
    occupiedPlaces: number;
    registrationMode: 'automatic' | 'manual';
    isCancelled: boolean;
    isStarted: boolean;
    isOrganizer: boolean;
    registrationStatus: EventRegistrationStatus | null;
};

export type EventParticipant = {
    id: number;
    displayName: string | null;
    avatar: AvatarOption | null;
};
export type OrganizerRegistration = EventParticipant & {
    registrationId: number;
    status: EventRegistrationStatus;
};

export type EventDetail = EventSummary & {
    detailedLocation?: string;
    participants?: EventParticipant[];
    registrations?: OrganizerRegistration[];
};

export type EventWorkspaceContext = 'discover' | 'mine';

export type EmbeddedMemberProfile = {
    member: PublicMember;
    canBlock: boolean;
    canLike: boolean;
    canUnblock: boolean;
};

export type EventPanel =
    | { kind: 'detail'; event: EventDetail }
    | { kind: 'create' }
    | { kind: 'edit'; event: EventDetail }
    | { kind: 'participants'; event: EventDetail }
    | {
          kind: 'participant-profile';
          event: EventDetail;
          profile: EmbeddedMemberProfile;
      }
    | { kind: 'registrations'; event: EventDetail };

export type EventWorkspaceProps = {
    context: EventWorkspaceContext;
    events?: EventSummary[];
    organized?: EventSummary[];
    participating?: EventSummary[];
    panel: EventPanel | null;
    closeHref: string;
};
