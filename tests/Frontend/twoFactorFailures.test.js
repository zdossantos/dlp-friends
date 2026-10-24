import { spawnSync } from 'node:child_process';
import { expect, mock, test } from 'bun:test';

const page = { props: { i18n: {} } };
mock.module('@inertiajs/vue3', () => ({
    usePage: () => page,
    useHttp: () => ({
        submit: async () => {
            throw new Error('offline');
        },
    }),
}));
const { useTwoFactorAuth } =
    await import('../../resources/js/composables/useTwoFactorAuth');

for (const [locale, expected] of [
    [
        'fr',
        [
            'Impossible de charger le QR code. Réessaie.',
            'Impossible de charger la clé de configuration. Réessaie.',
            'Impossible de charger les codes de récupération. Réessaie.',
        ],
    ],
    [
        'en',
        [
            'Unable to load the QR code. Please try again.',
            'Unable to load the setup key. Please try again.',
            'Unable to load the recovery codes. Please try again.',
        ],
    ],
]) {
    test(`two factor failures use the ${locale} catalogue and discard stale secrets`, async () => {
        const catalogue = JSON.parse(
            spawnSync(
                'php',
                [
                    '-r',
                    `echo json_encode(require 'lang/${locale}/account.php');`,
                ],
                { encoding: 'utf8' },
            ).stdout,
        );
        page.props.i18n = { locale, messages: { account: catalogue } };
        const auth = useTwoFactorAuth();
        auth.clearTwoFactorAuthData();
        auth.qrCodeSvg.value = '<svg>old</svg>';
        auth.manualSetupKey.value = 'old-secret';
        await auth.fetchSetupData();
        expect(auth.errors.value).toEqual([expected[0], expected[1]]);
        expect(auth.hasSetupData.value).toBe(false);
        expect(auth.qrCodeSvg.value).toBeNull();
        expect(auth.manualSetupKey.value).toBeNull();
        auth.recoveryCodesList.value = ['old-code'];
        await auth.fetchRecoveryCodes();
        expect(auth.errors.value).toEqual([expected[2]]);
        expect(auth.recoveryCodesList.value).toEqual([]);
    });
}
