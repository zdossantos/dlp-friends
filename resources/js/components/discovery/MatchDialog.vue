<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MatchCelebration from '@/components/discovery/MatchCelebration.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useTranslations } from '@/composables/useTranslations';
import type { MemberIdentity } from '@/types';

const props = withDefaults(
    defineProps<{
        open: boolean;
        match: MemberIdentity;
        conversationHref?: string;
        showContinue?: boolean;
        dismissible?: boolean;
        locked?: boolean;
    }>(),
    { dismissible: true, locked: false, showContinue: true },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    openConversation: [];
}>();
const { t } = useTranslations();
const { isDesktop, Modal } = useResponsiveModal();

function updateOpen(open: boolean): void {
    if (open || props.dismissible) {
        emit('update:open', open);
    }
}
</script>

<template>
    <MatchCelebration v-if="open" />
    <component :is="Modal.Root" :open="open" @update:open="updateOpen">
        <component
            :is="Modal.Content"
            :class="[
                'z-[60] overflow-hidden border-secondary-foreground/25 bg-secondary',
                { 'px-2 pb-8 *:px-4': !isDesktop },
            ]"
            v-bind="isDesktop ? { showCloseButton: dismissible } : {}"
        >
            <component :is="Modal.Header" class="relative z-10">
                <component
                    :is="Modal.Title"
                    data-test="match-heading"
                    class="text-secondary-foreground outline-none"
                    tabindex="-1"
                >
                    {{ t('discovery.match.title') }}
                </component>
                <div class="flex items-center gap-3 py-2">
                    <AvatarPortrait
                        data-test="match-member-avatar"
                        :avatar="match.avatar"
                        class="size-12 shrink-0 rounded-xl"
                    />
                    <p
                        data-test="match-member-name"
                        class="font-semibold text-secondary-foreground"
                    >
                        {{ match.displayName }}
                    </p>
                </div>
                <component
                    :is="Modal.Description"
                    class="text-secondary-foreground"
                >
                    {{
                        t('discovery.match.description', {
                            name: match.displayName,
                        })
                    }}
                </component>
            </component>
            <component :is="Modal.Footer" class="relative z-10">
                <Button v-if="conversationHref" as-child variant="outline">
                    <Link
                        :href="conversationHref"
                        data-test="open-match-conversation"
                        @click.capture="updateOpen(false)"
                    >
                        {{ t('discovery.match.open_conversation') }}
                    </Link>
                </Button>
                <Button
                    v-else
                    type="button"
                    variant="outline"
                    data-test="open-match-conversation"
                    :disabled="locked"
                    @click="emit('openConversation')"
                >
                    {{ t('discovery.match.open_conversation') }}
                </Button>
                <Button
                    v-if="showContinue"
                    type="button"
                    :aria-label="t('discovery.match.continue')"
                    @click="emit('update:open', false)"
                >
                    {{ t('discovery.match.continue') }}
                </Button>
            </component>
        </component>
    </component>
</template>
