import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { inject, provide, ref, shallowRef } from 'vue';
import type { InjectionKey, Ref, ShallowRef } from 'vue';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';
import {
    activeConversationId,
    selectMatchNotification,
    shouldShowMessageToast,
} from '@/lib/memberNotifications';
import { show as showConversation } from '@/routes/conversations';
import type { MemberIdentity, RealtimeConversationMessage } from '@/types';

export type MemberMatchNotification = {
    match_id: number;
    conversation_id: number;
    member: MemberIdentity;
};

type MemberRealtimeContext = {
    activeMatch: Ref<MemberMatchNotification | null>;
    latestMessage: ShallowRef<RealtimeConversationMessage | null>;
    presentMatch: (match: MemberMatchNotification) => void;
    dismissMatch: () => void;
};

const memberRealtimeKey: InjectionKey<MemberRealtimeContext> = Symbol(
    'member-realtime-notifications',
);

export function useMemberRealtimeNotifications(
    currentUserId: number,
): MemberRealtimeContext {
    const page = usePage();
    const { t } = useTranslations();
    const activeMatch = ref<MemberMatchNotification | null>(null);
    const latestMessage = shallowRef<RealtimeConversationMessage | null>(null);
    const seenMatchIds = new Set<number>();
    const seenMessageIds = new Set<number>();

    const presentMatch = (match: MemberMatchNotification): void => {
        if (seenMatchIds.has(match.match_id)) {
            return;
        }

        seenMatchIds.add(match.match_id);
        activeMatch.value = selectMatchNotification(activeMatch.value, match);
    };

    useEcho<MemberMatchNotification | RealtimeConversationMessage>(
        `App.Models.User.${currentUserId}`,
        ['.match.created', '.message.sent'],
        (notification) => {
            if ('match_id' in notification) {
                const pageMatch = (
                    page.props as typeof page.props & {
                        match?: { id: number } | null;
                    }
                ).match;

                if (
                    seenMatchIds.has(notification.match_id) ||
                    pageMatch?.id === notification.match_id ||
                    activeConversationId(page.url) ===
                        notification.conversation_id
                ) {
                    return;
                }

                presentMatch(notification);

                return;
            }

            if (seenMessageIds.has(notification.id)) {
                return;
            }

            seenMessageIds.add(notification.id);
            latestMessage.value = notification;

            if (
                !shouldShowMessageToast(notification, currentUserId, page.url)
            ) {
                return;
            }

            toast(notification.author.display_name, {
                id: `message-${notification.id}`,
                description: notification.content,
                descriptionClass: 'line-clamp-1',
                action: {
                    label: t('conversations.notification.open'),
                    onClick: () =>
                        router.visit(
                            showConversation(notification.conversation_id).url,
                        ),
                },
            });
        },
    );

    return {
        activeMatch,
        latestMessage,
        presentMatch,
        dismissMatch: () => {
            activeMatch.value = null;
        },
    };
}

export function provideMemberRealtimeContext(
    context: MemberRealtimeContext,
): void {
    provide(memberRealtimeKey, context);
}

export function useMemberRealtimeContext(): MemberRealtimeContext {
    const context = inject(memberRealtimeKey);

    if (!context) {
        throw new Error('Member realtime context is unavailable.');
    }

    return context;
}
