export type WebPushInvitationState = {
    standalone: boolean;
    supported: boolean;
    initialized: boolean;
    subscribed: boolean;
    permission: NotificationPermission;
    dismissed: boolean;
};

export type NotificationSettingsAction =
    'invite' | 'subscribe' | 'instructions';

export type NotificationSettingsView =
    'permission-denied' | 'unsupported' | 'controls';

export const WEB_PUSH_INVITATION_OPEN_EVENT =
    'web-push-invitation:open' as const;

export const invitationStorageKey = (userId: number): string =>
    `web-push-invitation-dismissed:${userId}`;

export const shouldOpenWebPushInvitation = (
    state: WebPushInvitationState,
): boolean =>
    state.standalone &&
    state.supported &&
    state.initialized &&
    !state.subscribed &&
    state.permission === 'default' &&
    !state.dismissed;

export const notificationSettingsAction = (
    permission: NotificationPermission,
): NotificationSettingsAction => {
    if (permission === 'denied') {
        return 'instructions';
    }

    return permission === 'granted' ? 'subscribe' : 'invite';
};

export const notificationSettingsView = ({
    permission,
    supported,
}: {
    permission: NotificationPermission;
    supported: boolean;
}): NotificationSettingsView => {
    if (permission === 'denied') {
        return 'permission-denied';
    }

    return supported ? 'controls' : 'unsupported';
};
