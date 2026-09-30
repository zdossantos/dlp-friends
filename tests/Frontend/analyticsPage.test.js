import { describe, expect, test } from 'bun:test';
import { resolveAnalyticsPage } from '../../resources/js/lib/analyticsPage';

describe('resolveAnalyticsPage', () => {
    test.each([
        ['/conversations', 'fr', 'conversation_list', 'Mes échanges — DLP Friends', '/conversations'],
        ['/conversations/42?from=Zacharie#latest', 'fr', 'conversation_detail', 'Conversation — DLP Friends', '/conversations/{id}'],
        ['/members/0198f30e-7b67-7260-9c7d-4f15d4da0d31', 'fr', 'member_profile', 'Profil membre — DLP Friends', '/members/{id}'],
        ['/profile', 'fr', 'own_profile', 'Mon profil — DLP Friends', '/profile'],
        ['/events/42/participants/84', 'fr', 'event_participant', 'Participant — DLP Friends', '/events/{id}/participants/{id}'],
        ['/events/42', 'fr', 'event_detail', 'Événement — DLP Friends', '/events/{id}'],
        ['/conversations', 'en', 'conversation_list', 'My conversations — DLP Friends', '/conversations'],
        ['/conversations/42', 'en', 'conversation_detail', 'Conversation — DLP Friends', '/conversations/{id}'],
        ['/members/42', 'en', 'member_profile', 'Member profile — DLP Friends', '/members/{id}'],
        ['/profile', 'en', 'own_profile', 'My profile — DLP Friends', '/profile'],
        ['/events/42/participants/84', 'en', 'event_participant', 'Participant — DLP Friends', '/events/{id}/participants/{id}'],
        ['/events/42', 'en', 'event_detail', 'Event — DLP Friends', '/events/{id}'],
    ])('%s resolves to a stable generic page', (path, locale, pageType, pageTitle, pagePath) => {
        expect(resolveAnalyticsPage(path, locale)).toEqual({
            pageType,
            pageTitle,
            pagePath,
        });
    });

    test('unknown routes use a generic fallback without leaking route content', () => {
        const page = resolveAnalyticsPage('/private/Zacharie?email=z@example.com#bio', 'fr');

        expect(page).toEqual({
            pageType: 'application_page',
            pageTitle: 'DLP Friends',
            pagePath: '/application',
        });
        expect(JSON.stringify(page)).not.toContain('z@example.com');
        expect(JSON.stringify(page)).not.toContain('bio');
        expect(JSON.stringify(page)).not.toContain('Zacharie');
    });
});
