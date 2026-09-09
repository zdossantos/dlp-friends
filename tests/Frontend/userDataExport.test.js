import { describe, expect, test } from 'bun:test';
import { userDataExportDownload } from '../../resources/js/lib/userDataExport';

describe('personal data export download', () => {
    test('returns the generated blob and server filename', async () => {
        const blob = new Blob(['{"account":{}}'], { type: 'application/json' });
        const result = await userDataExportDownload(async () =>
            new Response(blob, {
                headers: {
                    'Content-Disposition':
                        'attachment; filename=dlp-friends-data-2026-09-08.json',
                },
            }),
        );

        expect(await result.blob.text()).toBe('{"account":{}}');
        expect(result.filename).toBe('dlp-friends-data-2026-09-08.json');
    });

    test('rejects a failed generation', async () => {
        expect(
            userDataExportDownload(async () => new Response(null, { status: 500 })),
        ).rejects.toThrow('Personal data export failed');
    });
});
