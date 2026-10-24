import { afterEach, expect, test } from 'bun:test';
import { createRenderer } from 'vue';
import {
    initializeTheme,
    useAppearance,
} from '../../resources/js/composables/useAppearance';

const originalGlobals = new Map(
    ['window', 'document', 'localStorage'].map((key) => [
        key,
        Object.getOwnPropertyDescriptor(globalThis, key),
    ]),
);

afterEach(() => {
    for (const [key, descriptor] of originalGlobals) {
        if (descriptor) {
            Object.defineProperty(globalThis, key, descriptor);
        } else {
            delete globalThis[key];
        }
    }
});

const renderer = createRenderer({
    createComment: () => ({}),
    insert() {},
    remove() {},
    parentNode() {},
    nextSibling() {},
});

function browser(saved = 'system') {
    const listeners = new Set();
    const classes = new Set();
    const query = {
        matches: false,
        addEventListener: (_, listener) => listeners.add(listener),
        removeEventListener: (_, listener) => listeners.delete(listener),
    };
    globalThis.window = { matchMedia: () => query };
    globalThis.document = {
        cookie: '',
        documentElement: {
            classList: {
                toggle: (name, active) =>
                    active ? classes.add(name) : classes.delete(name),
            },
        },
    };
    globalThis.localStorage = {
        getItem: () => saved,
        setItem: (_, value) => (saved = value),
    };

    return {
        classes,
        changeSystem(dark) {
            query.matches = dark;
            listeners.forEach((listener) => listener(query));
        },
    };
}

function mountAppearance() {
    let theme;
    const failures = [];
    const app = renderer.createApp({
        setup() {
            theme = useAppearance();

            return () => null;
        },
    });
    app.config.errorHandler = (error) => failures.push(error);
    app.mount({});

    return { app, theme, failures };
}

test('system changes update both the rendered theme and its reactive consumers', () => {
    const environment = browser();
    initializeTheme();
    const { app, theme } = mountAppearance();
    expect(theme.resolvedAppearance.value).toBe('light');
    environment.changeSystem(true);
    expect(environment.classes.has('dark')).toBe(true);
    expect(theme.resolvedAppearance.value).toBe('dark');
    environment.changeSystem(false);
    expect(theme.resolvedAppearance.value).toBe('light');
    app.unmount();
});

test('invalid stored preferences fall back to the current system theme', () => {
    const environment = browser('invalid');
    environment.changeSystem(true);
    initializeTheme();
    const { app, theme } = mountAppearance();
    expect(theme.appearance.value).toBe('system');
    expect(theme.resolvedAppearance.value).toBe('dark');
    expect(environment.classes.has('dark')).toBe(true);
    app.unmount();
});

test('unavailable storage does not break theme initialization or mounting', () => {
    browser();
    localStorage.getItem = () => {
        throw new Error('storage disabled');
    };
    expect(() => initializeTheme()).not.toThrow();
    const { app, theme, failures } = mountAppearance();
    expect(failures).toEqual([]);
    expect(theme.appearance.value).toBe('system');
    app.unmount();
});

test('an explicit choice survives system changes and remounting when persistence fails', () => {
    const environment = browser('light');
    initializeTheme();
    const { app, theme } = mountAppearance();
    localStorage.setItem = () => {
        throw new Error('storage disabled');
    };
    expect(() => theme.updateAppearance('dark')).not.toThrow();
    expect(document.cookie).toContain('appearance=dark;');
    expect(environment.classes.has('dark')).toBe(true);
    environment.changeSystem(false);
    expect(theme.appearance.value).toBe('dark');
    expect(theme.resolvedAppearance.value).toBe('dark');
    expect(environment.classes.has('dark')).toBe(true);
    app.unmount();

    const remounted = mountAppearance();
    expect(remounted.failures).toEqual([]);
    expect(remounted.theme.appearance.value).toBe('dark');
    expect(remounted.theme.resolvedAppearance.value).toBe('dark');
    expect(environment.classes.has('dark')).toBe(true);
    environment.changeSystem(false);
    expect(environment.classes.has('dark')).toBe(true);
    remounted.app.unmount();
});
