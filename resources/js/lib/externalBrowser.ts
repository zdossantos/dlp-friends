// Destination is the fixed, server-rendered public landing URL.
export function externalBrowserUrl(
    destination: string,
    userAgent: string,
): string {
    const url = new URL(destination);

    if (/Android/i.test(userAgent)) {
        return `intent://${url.host}${url.pathname}${url.search}#Intent;scheme=${url.protocol.slice(0, -1)};action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;S.browser_fallback_url=${encodeURIComponent(destination)};end`;
    }

    if (/iPhone|iPad|iPod/i.test(userAgent)) {
        if (/Instagram/i.test(userAgent)) {
            return `instagram://extbrowser/?url=${encodeURIComponent(destination)}`;
        }

        if (url.protocol === 'https:') {
            return destination.replace(/^https:/, 'x-safari-https:');
        }
    }

    return destination;
}
