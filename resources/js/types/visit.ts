import type { AvatarOption } from '@/types';
export type VisitsVisibility = 'hidden' | 'matches' | 'members';
export type OwnVisit = { id: number; starts_on: string; ends_on: string };
export type VisitMember = {
    id: number;
    display_name: string;
    avatar: AvatarOption | null;
    is_match: boolean;
};
export type VisitDay = {
    date: string;
    isToday: boolean;
    ownVisitIds: number[];
    otherCount: number;
    preview: VisitMember[];
};
export type VisitFilters = {
    month: string;
    day: string;
    view: 'agenda' | 'calendar';
    audience: 'members' | 'matches';
    search: string;
    member: number | null;
};
export type VisitWorkspaceProps = {
    today: string;
    filters: VisitFilters;
    visibility: VisitsVisibility;
    ownVisits: OwnVisit[];
    days: VisitDay[];
    closeHref: string;
    panel: {
        kind: 'attendees';
        date: string;
        members: {
            data: VisitMember[];
            links: { prev: string | null; next: string | null };
            meta: { total: number; current_page: number; last_page: number };
        };
    } | null;
};
