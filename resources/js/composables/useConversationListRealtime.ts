import { useEcho } from '@laravel/echo-vue';
import type { ConversationMessage } from '@/types';

export function useConversationListRealtime(
    currentUserId: number,
    onMessage: (message: ConversationMessage) => void,
): void {
    useEcho<ConversationMessage>(
        `App.Models.User.${currentUserId}`,
        '.message.sent',
        onMessage,
    );
}
