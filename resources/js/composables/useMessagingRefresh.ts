import { useConnectionStatus } from '@laravel/echo-vue';
import { onMounted, onUnmounted, watch } from 'vue';

/** Reconcile server state after navigation, sleep and missed broadcasts. */
export function useMessagingRefresh(refresh: () => void): void {
    const status = useConnectionStatus();
    let timer: ReturnType<typeof setInterval> | undefined;
    let scheduled: ReturnType<typeof setTimeout> | undefined;

    function schedule(): void {
        if (document.visibilityState !== 'visible') {
            return;
        }

        clearTimeout(scheduled);
        scheduled = setTimeout(() => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        }, 150);
    }

    watch(status, (value) => {
        if (value === 'connected') {
            schedule();
        }
    });
    onMounted(() => {
        schedule();
        window.addEventListener('pageshow', schedule);
        window.addEventListener('online', schedule);
        document.addEventListener('visibilitychange', schedule);
        // Reverb delivery is best effort. Keep an open screen current even when
        // a connection looks healthy but a channel or queued event was missed.
        timer = setInterval(schedule, 15_000);
    });
    onUnmounted(() => {
        clearTimeout(scheduled);
        clearInterval(timer);
        window.removeEventListener('pageshow', schedule);
        window.removeEventListener('online', schedule);
        document.removeEventListener('visibilitychange', schedule);
    });
}
