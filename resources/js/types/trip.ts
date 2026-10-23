import type { AvatarOption } from '@/types';
export type TripMember = {
    id: number;
    display_name: string;
    avatar?: AvatarOption | null;
};
export type TripRegistration = {
    id: number;
    status: string;
    reason: string | null;
    member?: TripMember | null;
};
export type TripDetail = {
    chat_href?: string;
    id: number;
    transport?: string;
    direction?: string;
    general_departure?: string;
    destination?: string;
    departs_at?: string;
    arrives_at?: string | null;
    estimated_duration_minutes?: number | null;
    audience?: string;
    remaining_places?: number;
    car_seats?: number | null;
    cancelled_at?: string | null;
    cancellation_reason?: string | null;
    first_accepted_at?: string | null;
    organizer?: TripMember;
    registration: TripRegistration | null;
    can_manage: boolean;
    can_request: boolean;
    can_edit?: boolean;
    meeting_point?: string;
};
export type TripDeparture = {
    id: number;
    display_name: string | null;
    left_at: string;
    reason: string;
    can_report: boolean;
};
export type TripPage = {
    data: TripDetail[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
};
