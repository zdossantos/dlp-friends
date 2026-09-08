export type UserDataExportDownload = {
    blob: Blob;
    filename: string;
};

export async function userDataExportDownload(
    request: () => Promise<Response>,
): Promise<UserDataExportDownload> {
    const response = await request();

    if (!response.ok) {
        throw new Error('Personal data export failed');
    }

    const disposition = response.headers.get('Content-Disposition') ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1];

    return {
        blob: await response.blob(),
        filename: filename ?? 'dlp-friends-data.json',
    };
}
