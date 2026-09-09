import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    inject,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    shallowRef,
} from 'vue';
import type { InjectionKey, Ref, ShallowRef } from 'vue';
import { toast } from 'vue-sonner';
import { useTranslations } from '@/composables/useTranslations';
import { xsrfHeader } from '@/lib/csrf';
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
    presenceChanged: ShallowRef<MemberPresenceChanged | null>;
    presentMatch: (match: MemberMatchNotification) => void;
    dismissMatch: () => void;
};

export type MemberPresenceChanged = {
    user_id: number;
    online: boolean;
    last_active_at: string | null;
    expires_at: string | null;
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
    const presenceChanged = shallowRef<MemberPresenceChanged | null>(null);
    const seenMatchIds = new Set<number>();
    const seenMessageIds = new Set<number>();
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
    >(
        `App.Models.User.${currentUserId}`,
        ['.match.created', '.message.sent', '.presence.changed'],
        (notification) => {
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

    let heartbeatTimer: ReturnType<typeof setInterval> | undefined;
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
        heartbeat();
        heartbeatTimer = setInterval(heartbeat, 20_000);
        document.addEventListener('visibilitychange', heartbeat);
    });
    onBeforeUnmount(() => {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
        }

        document.removeEventListener('visibilitychange', heartbeat);
        presenceTimers.forEach(clearTimeout);
    });

    return {
        activeMatch,
        latestMessage,
        presenceChanged,
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
