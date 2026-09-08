export type UserDataExportState = {
    status: 'pending' | 'processing' | 'ready' | 'failed';
    expires_at: string | null;
    download_url: string | null;
} | null;
