import type { UserDataExportState } from '@/types';

type UserDataExportStatus = NonNullable<UserDataExportState>['status'];

export function isUserDataExportPreparing(
    status: UserDataExportStatus | undefined,
): boolean {
    return status === 'pending' || status === 'processing';
}

export function userDataExportBecameReady(
    previousStatus: UserDataExportStatus | undefined,
    status: UserDataExportStatus | undefined,
): boolean {
    return isUserDataExportPreparing(previousStatus) && status === 'ready';
}
