export type PartnerRevisionStatus =
    'draft' | 'pending_approval' | 'approved' | 'rejected';

export type PartnerRevision = {
    id: number;
    nameFr: string;
    nameEn: string;
    descriptionFr: string;
    descriptionEn: string;
    imageUrl: string | null;
    status: PartnerRevisionStatus;
    submittedAt: string | null;
    decidedAt: string | null;
    rejectionReason: string | null;
};

export type PartnerAnnouncementStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'sending'
    | 'sent'
    | 'rejected'
    | 'cancelled';

export type PartnerAnnouncement = {
    id: number;
    title: string;
    content: string;
    destinationUrl: string;
    status: PartnerAnnouncementStatus;
    submittedAt: string | null;
    decidedAt: string | null;
    rejectionReason: string | null;
    canEdit: boolean;
    canSubmit: boolean;
    canCancel: boolean;
    canRevise: boolean;
};

export type PartnerAnnouncementStatistics = {
    id: number;
    title: string;
    status: PartnerAnnouncementStatus;
    prepared: number;
    delivered: number;
    read: number;
    dismissed: number;
    unique_clicks: number;
    total_clicks: number;
    read_rate: number;
    dismiss_rate: number;
    unique_click_rate: number;
    partner_name?: string | null;
    pending?: number;
    failed?: number;
    skipped?: number;
};
