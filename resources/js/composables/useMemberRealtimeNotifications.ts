import { router, usePage } from '@inertiajs/vue3';
import { useEcho, useEchoNotification } from '@laravel/echo-vue';
import {
    inject,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    shallowRef,
    watch,
} from 'vue';
import type { InjectionKey, Ref, ShallowRef } from 'vue';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
import {
    eventChatAccessReloadOptions,
    isEventWorkspaceUrl,
} from '@/lib/eventChatAccess';
import type { EventChatAccessChanged } from '@/lib/eventChatAccess';
import {
    activeConversationId,
    registerNotification,
    selectMatchNotification,
    shouldShowMessageToast,
    shouldAcknowledgePersistentNotification,
} from '@/lib/memberNotifications';
import { index as conversationsIndex } from '@/routes/conversations';
import { show as showConversation } from '@/routes/conversations';
import { show as showGroupChat } from '@/routes/events/chat';
import type { MemberIdentity, RealtimeConversationMessage } from '@/types';
import type { GroupChatMessage } from '@/types/event';

export type MemberMatchNotification = {
    match_id: number;
    conversation_id: number;
    member: MemberIdentity;
};

type MemberRealtimeContext = {
    activeMatch: Ref<MemberMatchNotification | null>;
    latestMessage: ShallowRef<RealtimeConversationMessage | null>;
    latestGroupMessage: ShallowRef<GroupChatMessage | null>;
    latestPersistentNotification: ShallowRef<PersistentMemberNotification | null>;
    presenceChanged: ShallowRef<MemberPresenceChanged | null>;
    unreadNotificationsCount: Ref<number>;
    presentMatch: (match: MemberMatchNotification) => void;
    dismissMatch: () => void;
};

export type MemberPresenceChanged = {
    user_id: number;
    online: boolean;
    last_active_at: string | null;
    expires_at: string | null;
};

type PersistentMemberNotification = {
    id: string;
    category: string;
    translation_key: string;
    parameters: Record<string, string | number | null>;
    target_type: string;
    target_id: number;
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
    const latestGroupMessage = shallowRef<GroupChatMessage | null>(null);
    const latestPersistentNotification =
        shallowRef<PersistentMemberNotification | null>(null);
    const presenceChanged = shallowRef<MemberPresenceChanged | null>(null);
    const unreadNotificationsCount = ref(
        page.props.auth.unread_notifications_count,
    );
    const seenMatchIds = new Set<number>();
    const seenMessageIds = new Set<number>();
    const seenNotificationIds = new Set<string>();
    const presenceTimers = new Map<number, ReturnType<typeof setTimeout>>();

    const presentMatch = (match: MemberMatchNotification): void => {
        if (seenMatchIds.has(match.match_id)) {
            return;
        }

        seenMatchIds.add(match.match_id);
        activeMatch.value = selectMatchNotification(activeMatch.value, match);
    };

    useEcho<
        | MemberMatchNotification
        | RealtimeConversationMessage
        | MemberPresenceChanged
        | EventChatAccessChanged
        | GroupChatMessage
    >(
        `App.Models.User.${currentUserId}`,
        [
            '.match.created',
            '.message.sent',
            '.presence.changed',
            '.event-chat.access.changed',
            '.event-chat.message.sent',
        ],
        (notification) => {
            if ('access' in notification) {
                if (page.url.split(/[?#]/, 1)[0] === '/conversations') {
                    router.reload({ only: ['groups'] });
                }

                if (page.component === 'Conversations/Group') {
                    const event = page.props.event as { id: number };

                    if (event.id === notification.event_id) {
                        if (notification.access === 'revoked') {
                            router.visit(
                                conversationsIndex({
                                    query: { category: 'groups' },
                                }),
                            );
                        } else {
                            router.reload({ only: ['chat'] });
                        }
                    }
                } else if (page.component === 'Conversations/Index') {
                    router.reload({ only: ['groups'] });
                } else if (isEventWorkspaceUrl(page.url)) {
                    router.reload(eventChatAccessReloadOptions(notification));
                }

                return;
            }

            if ('group_chat_id' in notification) {
                latestGroupMessage.value = notification;

                return;
            }

            if ('online' in notification) {
                presenceChanged.value = notification;
                const existingTimer = presenceTimers.get(notification.user_id);

                if (existingTimer) {
                    clearTimeout(existingTimer);
                }

                if (notification.online && notification.expires_at) {
                    const delay = Math.max(
                        0,
                        Date.parse(notification.expires_at) - Date.now(),
                    );
                    presenceTimers.set(
                        notification.user_id,
                        setTimeout(() => {
                            presenceChanged.value = {
                                ...notification,
                                online: false,
                                expires_at: null,
                            };
                        }, delay),
                    );
                }

                return;
            }

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

    useEchoNotification<PersistentMemberNotification>(
        `App.Models.User.${currentUserId}`,
        (notification) => {
            if (!registerNotification(seenNotificationIds, notification)) {
                return;
            }

            unreadNotificationsCount.value += 1;
            latestPersistentNotification.value = notification;

            if (
                notification.target_type === 'event_chat' &&
                !(
                    page.component === 'Conversations/Group' &&
                    (page.props.event as { id: number }).id ===
                        notification.target_id
                ) &&
                !shouldAcknowledgePersistentNotification(notification, page.url)
            ) {
                toast(
                    t('notifications.items.new_group_message', {
                        sender: notification.parameters.sender ?? '',
                        group: notification.parameters.group ?? '',
                    }),
                    {
                        id: notification.id,
                        action: {
                            label: t('conversations.notification.open'),
                            onClick: () =>
                                router.visit(
                                    showGroupChat(notification.target_id).url,
                                ),
                        },
                    },
                );
            }

            if (
                new URL(page.url, window.location.origin).pathname ===
                '/notifications'
            ) {
                router.reload({ only: ['auth', 'notifications'] });
            }
        },
    );

    watch(
        () => page.props.auth.unread_notifications_count,
        (count) => {
            unreadNotificationsCount.value = count;
        },
    );

    watch(
        unreadNotificationsCount,
        (count) => {
            if (!('setAppBadge' in navigator)) {
                return;
            }

            if (count > 0) {
                void navigator.setAppBadge(count).catch(() => undefined);
            } else {
                void navigator.clearAppBadge().catch(() => undefined);
            }
        },
        { immediate: true },
    );

    let heartbeatTimer: ReturnType<typeof setInterval> | undefined;
    let initialHeartbeatTimer: ReturnType<typeof setTimeout> | undefined;
    const heartbeat = (): void => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        void fetch('/presence/heartbeat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...xsrfHeader(document.cookie),
            },
        }).catch(() => undefined);
    };
    onMounted(() => {
        initialHeartbeatTimer = setTimeout(heartbeat, 5_000);
        heartbeatTimer = setInterval(heartbeat, 20_000);
        document.addEventListener('visibilitychange', heartbeat);
    });
    onBeforeUnmount(() => {
        if (initialHeartbeatTimer) {
            clearTimeout(initialHeartbeatTimer);
        }

        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
        }

        document.removeEventListener('visibilitychange', heartbeat);
        presenceTimers.forEach(clearTimeout);
    });

    return {
        activeMatch,
        latestMessage,
        latestGroupMessage,
        latestPersistentNotification,
        presenceChanged,
        unreadNotificationsCount,
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
