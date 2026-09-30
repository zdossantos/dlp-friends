export type AppMode = 'pwa' | 'browser';

type AppModeRuntime = {
    standaloneMedia: boolean;
    iosStandalone?: boolean;
};

function browserRuntime(): AppModeRuntime {
    if (typeof window === 'undefined') {
        return { standaloneMedia: false };
    }

    return {
        standaloneMedia: window.matchMedia('(display-mode: standalone)').matches,
        iosStandalone: Boolean(
            (window.navigator as Navigator & { standalone?: boolean }).standalone,
        ),
    };
}

export function resolveAppMode(runtime: AppModeRuntime = browserRuntime()): AppMode {
    return runtime.standaloneMedia || runtime.iosStandalone === true
        ? 'pwa'
        : 'browser';
}
