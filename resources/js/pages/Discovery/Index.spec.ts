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
    onError?: (errors: Record<string, string>) => void;
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
    commonPassionCount: 3,
    commonPassions: ['Attractions', 'Parades', 'Pins'],
    frequencyBonus: true,
    score: 3.25,
};

function mountPage(props: {
    suggestion?: DiscoveryProfile | null;
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

describe('Discovery/Index', () => {
    afterEach(() => {
        postMock.mockReset();
        document.body.innerHTML = '';
    });

    it('renders loading, empty and suggested profile states', () => {
        expect(mountPage({ suggestion: undefined }).text()).toContain(
            'Recherche de profils…',
        );

        const emptyWrapper = mountPage({ suggestion: null });
        expect(emptyWrapper.text()).toContain(
            'Vous avez exploré tous les profils disponibles',
        );
        expect(emptyWrapper.get('a[href="/profile"]').text()).toContain(
            'Mon profil',
        );

        const cardWrapper = mountPage({ suggestion });
        expect(cardWrapper.findComponent(SwipeCard).exists()).toBe(true);
        expect(cardWrapper.text()).toContain('Mina Parade');
        expect(cardWrapper.text()).toContain('Attractions');
    });

    it('renders a dismissible match dialog with explicit title and description', async () => {
        mountPage({
            suggestion: null,
            match: { id: 99, displayName: 'Noa Orbit' },
        });
        await nextTick();

        expect(
            document.body.querySelector('[data-slot="dialog-title"]')
                ?.textContent,
        ).toContain('C’est un match !');
        expect(
            document.body.querySelector('[data-slot="dialog-description"]')
                ?.textContent,
        ).toContain('Noa Orbit');

        document
            .querySelector<HTMLButtonElement>(
                'button[aria-label="Continuer à découvrir"]',
            )
            ?.click();
        await nextTick();

        expect(
            document.body.querySelector('[data-slot="dialog-title"]'),
        ).toBeNull();
    });

    it('guards duplicate decisions, preserves the suggestion on validation failure and retries the last decision', async () => {
        const wrapper = mountPage({ suggestion });
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

        getPostOptions().onError?.({
            decision: 'Impossible d’enregistrer cette décision.',
        });
        await nextTick();

        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Impossible d’enregistrer cette décision.',
        );
        expect(wrapper.text()).toContain('Mina Parade');

        card.vm.$emit('like');
        expect(router.post).toHaveBeenCalledOnce();

        getPostOptions().onFinish?.();
        await nextTick();

        expect(card.props('locked')).toBe(false);

        await wrapper.get('button[aria-label="Réessayer"]').trigger('click');

        expect(router.post).toHaveBeenCalledTimes(2);
        expect(router.post).toHaveBeenLastCalledWith(
            '/discover/42/swipe',
            { decision: 'like' },
            expect.any(Object),
        );
    });
});
