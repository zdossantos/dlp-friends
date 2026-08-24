import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import SwipeCard from '@/components/discovery/SwipeCard.vue';
import type { DiscoveryMatch, DiscoveryProfile } from '@/types';
import DiscoveryIndex from './Index.vue';

type PostOptions = {
    preserveScroll?: boolean;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onHttpException?: () => boolean | void;
    onNetworkError?: () => boolean | void;
    onFinish?: () => void;
};

const postMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: {
        props: ['href'],
        template: '<a :href="href.url ?? href"><slot /></a>',
    },
    router: {
        post: postMock,
    },
}));

vi.mock('@/routes/discovery', () => ({
    swipe: (target: number) => ({ url: `/discover/${target}/swipe` }),
}));

vi.mock('@/routes/member-profile', () => ({
    show: () => ({ url: '/profile' }),
}));

const suggestion: DiscoveryProfile = {
    userId: 42,
    profileId: 7,
    displayName: 'Mina Parade',
    age: 29,
    bio: 'Toujours partante pour une journee attractions et spectacles.',
    visitFrequency: 'often',
    commonInterestCount: 3,
    commonInterests: ['Attractions', 'Parades', 'Pins'],
    frequencyBonus: true,
    score: 3.25,
};

function mountPage(props: {
    suggestions?: DiscoveryProfile[];
    match?: DiscoveryMatch | null;
}): VueWrapper {
    return mount(DiscoveryIndex, {
        attachTo: document.body,
        props: {
            match: null,
            ...props,
        },
    });
}

function getPostOptions(callIndex = 0): PostOptions {
    return postMock.mock.calls[callIndex]?.[2] as PostOptions;
}

function matchDialogTitle(): Element | null {
    return document.body.querySelector('[data-slot="dialog-title"]');
}

async function dismissMatchDialog(): Promise<void> {
    document
        .querySelector<HTMLButtonElement>(
            'button[aria-label="Continuer à découvrir"]',
        )
        ?.click();
    await nextTick();
}

describe('Discovery/Index', () => {
    afterEach(() => {
        postMock.mockReset();
        document.body.innerHTML = '';
    });

    it('renders loading, empty and a preloaded stack of up to five profiles', () => {
        expect(mountPage({ suggestions: undefined }).text()).toContain(
            'Recherche de profils…',
        );

        const emptyWrapper = mountPage({ suggestions: [] });
        expect(emptyWrapper.text()).toContain(
            'Vous avez exploré tous les profils disponibles',
        );
        expect(emptyWrapper.get('a[href="/profile"]').text()).toContain(
            'Mon profil',
        );

        const suggestions = Array.from({ length: 5 }, (_, index) => ({
            ...suggestion,
            userId: suggestion.userId + index,
            profileId: suggestion.profileId + index,
            displayName: `Profil ${index + 1}`,
        }));
        const cardWrapper = mountPage({ suggestions });
        const cards = cardWrapper.findAllComponents(SwipeCard);
        expect(cards).toHaveLength(5);
        expect(cards[0]?.props('locked')).toBe(false);
        expect(cards.slice(1).every((card) => card.props('locked'))).toBe(true);
        expect(
            cardWrapper
                .findAll('[data-test="discovery-card-stack-item"]')
                .slice(1)
                .every(
                    (item) =>
                        item.attributes('aria-hidden') === 'true' &&
                        item.attributes('inert') !== undefined,
                ),
        ).toBe(true);
        expect(cardWrapper.text()).toContain('Profil 1');
        expect(cardWrapper.text()).toContain('Attractions');
        expect(cardWrapper.get('main').classes()).toContain('max-w-md');
        expect(
            cardWrapper.find('[data-test="desktop-discovery-intro"]').exists(),
        ).toBe(false);
    });

    it('renders a dismissible match dialog with explicit title and description', async () => {
        mountPage({
            suggestions: [],
            match: { id: 99, displayName: 'Noa Orbit' },
        });
        await nextTick();

        expect(matchDialogTitle()?.textContent).toContain('C’est un match !');
        expect(
            document.body.querySelector('[data-slot="dialog-description"]')
                ?.textContent,
        ).toContain('Noa Orbit');

        await dismissMatchDialog();

        expect(matchDialogTitle()).toBeNull();
    });

    it('opens the match dialog for a new Inertia match prop and keeps a dismissed match closed until a new match arrives', async () => {
        const wrapper = mountPage({ suggestions: [], match: null });

        expect(matchDialogTitle()).toBeNull();

        await wrapper.setProps({
            match: { id: 101, displayName: 'Lina Castle' },
        });
        await nextTick();

        expect(matchDialogTitle()?.textContent).toContain('C’est un match !');
        expect(
            document.body.querySelector('[data-slot="dialog-description"]')
                ?.textContent,
        ).toContain('Lina Castle');

        await dismissMatchDialog();

        expect(matchDialogTitle()).toBeNull();

        await wrapper.setProps({
            match: { id: 101, displayName: 'Lina Castle' },
        });
        await nextTick();

        expect(matchDialogTitle()).toBeNull();

        await wrapper.setProps({
            match: { id: 102, displayName: 'Nora Parade' },
        });
        await nextTick();

        expect(matchDialogTitle()?.textContent).toContain('C’est un match !');
        expect(
            document.body.querySelector('[data-slot="dialog-description"]')
                ?.textContent,
        ).toContain('Nora Parade');
    });

    it('guards duplicate decisions, preserves the suggestion on validation failure and retries the last decision', async () => {
        const wrapper = mountPage({ suggestions: [suggestion] });
        const card = wrapper.getComponent(SwipeCard);

        card.vm.$emit('like');
        card.vm.$emit('like');

        expect(router.post).toHaveBeenCalledOnce();
        expect(router.post).toHaveBeenCalledWith(
            '/discover/42/swipe',
            { decision: 'like' },
            expect.any(Object),
        );
        expect(getPostOptions().preserveScroll).toBe(true);
        await nextTick();
        expect(card.props('locked')).toBe(true);

        await wrapper.setProps({ suggestions: undefined });
        getPostOptions().onError?.({
            decision: 'Impossible d’enregistrer cette décision.',
        });
        getPostOptions().onFinish?.();
        await wrapper.setProps({ suggestions: [suggestion] });
        await nextTick();

        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Impossible d’enregistrer cette décision.',
        );
        expect(wrapper.text()).toContain('Mina Parade');

        expect(router.post).toHaveBeenCalledOnce();

        expect(wrapper.getComponent(SwipeCard).props('locked')).toBe(false);

        await wrapper.get('button[aria-label="Réessayer"]').trigger('click');

        expect(router.post).toHaveBeenCalledTimes(2);
        expect(router.post).toHaveBeenLastCalledWith(
            '/discover/42/swipe',
            { decision: 'like' },
            expect.any(Object),
        );
    });

    it('never retries an old decision against a replacement suggestion', async () => {
        const wrapper = mountPage({ suggestions: [suggestion] });

        wrapper.getComponent(SwipeCard).vm.$emit('like');
        getPostOptions().onError?.({ target: 'Profil indisponible.' });
        getPostOptions().onFinish?.();
        await nextTick();

        await wrapper.setProps({ suggestions: undefined });
        await wrapper.setProps({
            suggestions: [
                {
                    ...suggestion,
                    userId: 84,
                    displayName: 'Nouvelle suggestion',
                },
            ],
        });

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('button[aria-label="Réessayer"]').exists()).toBe(
            false,
        );
        expect(router.post).toHaveBeenCalledOnce();
    });

    it('retains the card and exposes retry after an unexpected HTTP exception', async () => {
        const wrapper = mountPage({ suggestions: [suggestion] });

        wrapper.getComponent(SwipeCard).vm.$emit('pass');
        const handled = getPostOptions().onHttpException?.();
        getPostOptions().onFinish?.();
        await nextTick();

        expect(handled).toBe(false);
        expect(wrapper.text()).toContain('Mina Parade');
        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Le serveur n’a pas pu enregistrer cette décision.',
        );

        await wrapper.get('button[aria-label="Réessayer"]').trigger('click');

        expect(router.post).toHaveBeenLastCalledWith(
            '/discover/42/swipe',
            { decision: 'pass' },
            expect.any(Object),
        );
    });
});
