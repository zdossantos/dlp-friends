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
