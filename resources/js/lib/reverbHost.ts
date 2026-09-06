const loopbackHosts = new Set(['localhost', '127.0.0.1', '::1']);

export function resolveReverbHost(
    configuredHost: string,
    pageHost: string,
): string {
    return loopbackHosts.has(configuredHost) ? pageHost : configuredHost;
}
