import { onMounted, onUnmounted, ref, watch } from 'vue';
import type { CSSProperties } from 'vue';

/** Size full-screen chats to the space above the software keyboard. */
export function useMessagingViewport(enabled: () => boolean) {
    const style = ref<CSSProperties>();
    let viewport: VisualViewport | null = null;

    function update(): void {
        if (!enabled() || !viewport || Math.abs(viewport.scale - 1) > 0.05) {
            style.value = undefined;

            return;
        }

        style.value = {
            position: 'fixed',
            top: `${viewport.offsetTop}px`,
            left: '0',
            height: `${viewport.height}px`,
        };
    }

    watch(enabled, update);
    onMounted(() => {
        viewport = window.visualViewport;
        viewport?.addEventListener('resize', update);
        viewport?.addEventListener('scroll', update);
        update();
    });
    onUnmounted(() => {
        viewport?.removeEventListener('resize', update);
        viewport?.removeEventListener('scroll', update);
    });

    return style;
}
