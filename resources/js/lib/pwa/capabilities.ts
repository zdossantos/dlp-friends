export type PwaRuntimeState =
    'browser' | 'standalone' | 'installable' | 'installed' | 'unsupported';

export type PwaCapabilities = {
    standaloneMedia: boolean;
    iosStandalone: boolean;
    installed?: boolean;
    installPromptAvailable?: boolean;
    serviceWorkerSupported?: boolean;
};

export const detectPwaState = (
    capabilities: PwaCapabilities,
): PwaRuntimeState => {
    if (capabilities.standaloneMedia || capabilities.iosStandalone) {
        return 'standalone';
    }

    if (capabilities.installed) {
        return 'installed';
    }

    if (capabilities.installPromptAvailable) {
        return 'installable';
    }

    return capabilities.serviceWorkerSupported === false
        ? 'unsupported'
        : 'browser';
};

export const createSingleReloadHandler = (reload: () => void): (() => void) => {
    let reloaded = false;

    return () => {
        if (reloaded) {
            return;
        }

        reloaded = true;
        reload();
    };
};

export const isSafeExternalUrl = (url: string, origin: string): boolean => {
    try {
        const parsed = new URL(url, origin);

        return parsed.protocol === 'https:' && parsed.origin !== origin;
    } catch {
        return false;
    }
};

export const isIosDevice = (navigatorLike: {
    userAgent: string;
    platform?: string;
    maxTouchPoints?: number;
}): boolean =>
    /iPad|iPhone|iPod/.test(navigatorLike.userAgent) ||
    (navigatorLike.platform === 'MacIntel' &&
        (navigatorLike.maxTouchPoints ?? 0) > 1);
