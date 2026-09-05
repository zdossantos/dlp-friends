const defaultAppName = 'DLP Friends';

export function resolvePageTitle(
    title: string,
    configuredAppName?: string,
): string {
    const appName = configuredAppName?.trim() || defaultAppName;

    return title ? `${title} - ${appName}` : appName;
}
