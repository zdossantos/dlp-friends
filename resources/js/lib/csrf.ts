export function xsrfHeader(cookie: string): Record<string, string> {
    const token = cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return token === undefined
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(token) };
}
