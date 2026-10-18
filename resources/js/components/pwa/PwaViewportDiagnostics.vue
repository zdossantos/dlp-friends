<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { t } = useTranslations();
const { copy, copied } = useClipboard();
const active = ref(false);
const copyFailed = ref(false);
const probes = useTemplateRef<HTMLElement>('probes');
const samples = ref<ReturnType<typeof measure>[]>([]);
let listeners: AbortController | undefined;
let settled: ReturnType<typeof setTimeout> | undefined;

function rectangle(element: Element | null | undefined) {
    if (!element) {
        return null;
    }

    const { top, bottom, width, height } = element.getBoundingClientRect();

    return { top, bottom, width, height };
}

function measure(event: string) {
    const visual = window.visualViewport;
    const style = probes.value ? getComputedStyle(probes.value) : null;

    return {
        event,
        elapsed: Math.round(performance.now()),
        window: {
            width: window.innerWidth,
            height: window.innerHeight,
            scrollY: window.scrollY,
        },
        document: {
            height: document.documentElement.clientHeight,
            scrollHeight: document.documentElement.scrollHeight,
        },
        screen: {
            width: screen.width,
            height: screen.height,
            pixelRatio: window.devicePixelRatio,
        },
        visual: visual
            ? {
                  width: visual.width,
                  height: visual.height,
                  offsetTop: visual.offsetTop,
                  offsetLeft: visual.offsetLeft,
                  scale: visual.scale,
              }
            : null,
        css: Object.fromEntries(
            Array.from(probes.value?.children ?? []).map((element) => [
                (element as HTMLElement).dataset.unit,
                element.getBoundingClientRect().height,
            ]),
        ),
        safeArea: { top: style?.paddingTop, bottom: style?.paddingBottom },
        shell: rectangle(
            document.querySelector(
                '[data-test="member-shell-content"], [data-test="admin-shell-content"]',
            ),
        ),
        navigation: rectangle(
            document.querySelector(
                '[data-test="member-bottom-navigation"], [data-test="admin-bottom-navigation"]',
            ),
        ),
        focusedTag: document.activeElement?.tagName,
        visibility: document.visibilityState,
    };
}

const report = computed(() =>
    JSON.stringify(
        {
            version: 1,
            viewportMeta: document.querySelector<HTMLMetaElement>(
                'meta[name="viewport"]',
            )?.content,
            statusBarStyle: document.querySelector<HTMLMetaElement>(
                'meta[name="apple-mobile-web-app-status-bar-style"]',
            )?.content,
            standalone:
                window.matchMedia('(display-mode: standalone)').matches ||
                (navigator as Navigator & { standalone?: boolean })
                    .standalone === true,
            samples: samples.value,
        },
        null,
        2,
    ),
);

function capture(event = 'manual') {
    if (!active.value) {
        return;
    }

    samples.value = [...samples.value.slice(-39), measure(event)];
}

function changed(event: Event) {
    capture(event.type);
    clearTimeout(settled);
    // iOS can update viewport metrics after the orientation/focus event itself.
    settled = setTimeout(() => capture(`${event.type}:settled`), 300);
}

async function start() {
    active.value = true;
    await nextTick();
    capture('start');
    listeners = new AbortController();
    const options = { signal: listeners.signal, passive: true };

    for (const name of ['resize', 'orientationchange', 'focus', 'pageshow']) {
        window.addEventListener(name, changed, options);
    }

    document.addEventListener('visibilitychange', changed, options);
    window.visualViewport?.addEventListener('resize', changed, options);
    window.visualViewport?.addEventListener('scroll', changed, options);
}

function stop() {
    listeners?.abort();
    clearTimeout(settled);
    active.value = false;
    samples.value = [];
    copyFailed.value = false;
}

async function copyReport() {
    copyFailed.value = false;

    try {
        await copy(report.value);
    } catch {
        copyFailed.value = true;
    }
}

onBeforeUnmount(stop);
</script>

<template>
    <aside
        v-if="page.props.environment.isBeta"
        data-test="viewport-diagnostics"
        class="fixed top-[max(0.5rem,env(safe-area-inset-top))] right-2 z-[80] max-w-[min(19rem,calc(100vw-1rem))] rounded-xl border bg-card p-2 text-card-foreground shadow-lg"
        :aria-label="t('common.pwa.diagnostics_title')"
    >
        <Button
            v-if="!active"
            data-test="viewport-diagnostics-start"
            variant="outline"
            type="button"
            class="min-h-11"
            @click="start"
        >
            {{ t('common.pwa.diagnostics_title') }}
        </Button>
        <template v-else>
            <p class="mb-2 text-sm">
                {{ t('common.pwa.diagnostics_description') }}
            </p>
            <div class="flex flex-wrap gap-1">
                <Button
                    data-test="viewport-diagnostics-capture"
                    variant="outline"
                    type="button"
                    class="min-h-11"
                    @click="capture()"
                    >{{ t('common.pwa.diagnostics_capture') }}</Button
                >
                <Button type="button" class="min-h-11" @click="copyReport">{{
                    t(
                        copied
                            ? 'common.pwa.diagnostics_copied'
                            : 'common.pwa.diagnostics_copy',
                    )
                }}</Button>
                <Button
                    data-test="viewport-diagnostics-stop"
                    variant="outline"
                    type="button"
                    class="min-h-11"
                    @click="stop"
                    >{{ t('common.actions.close') }}</Button
                >
            </div>
            <p v-if="copyFailed" role="status" class="text-sm">
                {{ t('common.pwa.diagnostics_copy_failed') }}
            </p>
            <details class="mt-2 text-xs">
                <summary>{{ t('common.pwa.diagnostics_report') }}</summary>
                <pre
                    data-test="viewport-report"
                    class="max-h-40 overflow-auto select-text"
                    >{{ report }}</pre>
            </details>
        </template>
    </aside>
    <div
        v-if="active"
        ref="probes"
        aria-hidden="true"
        class="pointer-events-none invisible fixed top-0 left-0 w-0 [padding-top:env(safe-area-inset-top)] [padding-bottom:env(safe-area-inset-bottom)]"
    >
        <div data-unit="svh" class="fixed top-0 h-svh w-0" />
        <div data-unit="dvh" class="fixed top-0 h-dvh w-0" />
        <div data-unit="lvh" class="fixed top-0 h-lvh w-0" />
        <div data-unit="vh" class="fixed top-0 h-screen w-0" />
        <div data-unit="fixed" class="fixed inset-y-0 w-0" />
    </div>
</template>
