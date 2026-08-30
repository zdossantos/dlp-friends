import type { ToasterProps } from 'vue-sonner';

type ToasterPosition = NonNullable<ToasterProps['position']>;

export function resolveToasterPosition(
    isMobile: boolean,
    desktopPosition: ToasterPosition = 'bottom-right',
): ToasterPosition {
    return isMobile ? 'top-center' : desktopPosition;
}
