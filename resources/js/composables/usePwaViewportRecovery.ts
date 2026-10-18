import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { isIosDevice } from '@/lib/pwa/capabilities';

/** Keep WebKit's native scroll view expanded after a stale standalone resize. */
export function usePwaViewportRecovery(): void {
    let probe: HTMLDivElement | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let removeNavigate: (() => void) | undefined;
    let recovered = false;

    function check(): void {
        if (
            !probe ||
            document.visibilityState !== 'visible' ||
            !isIosDevice(navigator) ||
            !(
                navigator.standalone ||
                window.matchMedia('(display-mode: standalone)').matches
            )
        ) {
            return;
        }

        const focused = document.activeElement;

        if (
            focused instanceof HTMLElement &&
            (focused.matches('input, textarea, select') ||
                focused.isContentEditable)
        ) {
            return;
        }

        if (Math.abs((window.visualViewport?.scale ?? 1) - 1) > 0.05) {
            return;
        }

        // The diagnostic overflow probe restores the native viewport, but removing
        // it makes iOS shrink again. Keep the CSS-sized extent until layout disposal.
        if (probe.getBoundingClientRect().height - window.innerHeight > 48) {
            document.documentElement.style.setProperty(
                '--pwa-scroll-extent',
                'calc(100lvh + 1px)',
            );
            recovered = true;
        }
    }

    function schedule(): void {
        clearTimeout(timer);
        timer = setTimeout(check, 300);
    }

    onMounted(() => {
        probe = document.createElement('div');
        probe.style.cssText =
            'position:fixed;top:0;width:0;height:100lvh;visibility:hidden;pointer-events:none;contain:strict';
        document.body.append(probe);

        for (const event of [
            'pageshow',
            'focus',
            'resize',
            'orientationchange',
        ]) {
            window.addEventListener(event, schedule);
        }

        document.addEventListener('visibilitychange', schedule);
        document.addEventListener('focusout', schedule);
        removeNavigate = router.on('navigate', schedule);
        schedule();
    });

    onUnmounted(() => {
        clearTimeout(timer);
        probe?.remove();

        for (const event of [
            'pageshow',
            'focus',
            'resize',
            'orientationchange',
        ]) {
            window.removeEventListener(event, schedule);
        }

        document.removeEventListener('visibilitychange', schedule);
        document.removeEventListener('focusout', schedule);
        removeNavigate?.();

        if (recovered) {
            document.documentElement.style.removeProperty(
                '--pwa-scroll-extent',
            );
        }
    });
}
