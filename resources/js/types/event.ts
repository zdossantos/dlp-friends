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

export type EventParticipant = { id: number; displayName: string | null };
export type OrganizerRegistration = EventParticipant & {
    registrationId: number;
    status: EventRegistrationStatus;
};

export type EventDetail = EventSummary & {
    detailedLocation?: string;
    participants?: EventParticipant[];
    registrations?: OrganizerRegistration[];
};
