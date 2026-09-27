import { describe, expect, test } from 'bun:test';
import {
    invitationStorageKey,
    notificationSettingsAction,
    notificationSettingsView,
    shouldOpenWebPushInvitation,
} from '../../resources/js/lib/pwa/webPushInvitation';

describe('Web Push invitation', () => {
    test('opens for an existing member whose installed PWA has never requested permission', () => {
        expect(
            shouldOpenWebPushInvitation({
                standalone: true,
                supported: true,
                initialized: true,
                subscribed: false,
                permission: 'default',
                dismissed: false,
            }),
        ).toBe(true);
    });

    test.each([
        [
            'initial subscription check',
            true,
            true,
            false,
            false,
            'default',
            false,
        ],
        ['browser usage', false, true, true, false, 'default', false],
        ['unsupported push', true, false, true, false, 'default', false],
        ['existing subscription', true, true, true, true, 'granted', false],
        ['dismissed invitation', true, true, true, false, 'default', true],
        ['system refusal', true, true, true, false, 'denied', false],
    ])(
        'does not open after %s',
        (
            _scenario,
            standalone,
            supported,
            initialized,
            subscribed,
            permission,
            dismissed,
        ) => {
            expect(
                shouldOpenWebPushInvitation({
                    standalone,
                    supported,
                    initialized,
                    subscribed,
                    permission,
                    dismissed,
                }),
            ).toBe(false);
        },
    );

    test('does not mistake an insecure origin for a system refusal', () => {
        expect(
            notificationSettingsView({
                permission: 'denied',
                supported: false,
                secureContext: false,
            }),
        ).toBe('insecure-context');
    });

    test('shows installation guidance only when permission has not been denied', () => {
        expect(
            notificationSettingsView({
                permission: 'default',
                supported: false,
                secureContext: true,
            }),
        ).toBe('unsupported');
    });

    test('shows recovery guidance after a real refusal in a secure context', () => {
        expect(
            notificationSettingsView({
                permission: 'denied',
                supported: true,
                secureContext: true,
            }),
        ).toBe('permission-denied');
    });

    test('scopes a refusal to one member on one device', () => {
        expect(invitationStorageKey(42)).toBe(
            'web-push-invitation-dismissed:42',
        );
    });

    test.each([
        ['default', 'invite'],
        ['granted', 'subscribe'],
        ['denied', 'instructions'],
    ])(
        'selects the manual action for %s permission',
        (permission, expected) => {
            expect(notificationSettingsAction(permission)).toBe(expected);
        },
    );
});
