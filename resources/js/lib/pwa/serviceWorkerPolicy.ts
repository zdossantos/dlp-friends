const HASHED_BUILD_ASSET =
    /^\/build\/assets\/[a-zA-Z0-9_./-]+-[a-zA-Z0-9_-]{6,}\.(?:css|js|woff2?)$/;
const PUBLIC_PWA_ASSET = /^\/pwa\/icon(?:-maskable)?-(?:192|512)\.png$/;

export function isCacheableStaticRequest(request: Request): boolean {
    if (request.method !== 'GET') {
        return false;
    }

    if (
        request.headers.has('authorization') ||
        request.headers.has('x-inertia') ||
        request.headers.get('accept')?.includes('text/html')
    ) {
        return false;
    }

    const { pathname } = new URL(request.url);

    return (
        HASHED_BUILD_ASSET.test(pathname) ||
        PUBLIC_PWA_ASSET.test(pathname) ||
        pathname === '/favicon.svg' ||
        pathname === '/offline.html'
    );
}
