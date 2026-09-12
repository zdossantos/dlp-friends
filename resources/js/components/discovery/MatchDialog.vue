<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
    <Teleport to="body">
        <div
            v-if="open"
            data-test="match-celebration-layer"
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 z-[55] overflow-hidden"
        >
            <div
                data-test="match-magic"
                aria-hidden="true"
                class="absolute inset-0"
            >
                <span
                    class="motion-match-halo absolute top-1/2 left-1/2 size-48 -translate-x-1/2 -translate-y-1/2 rounded-full border border-amber-200/40"
                />
                <span
                    class="motion-match-halo absolute top-1/2 left-1/2 size-72 -translate-x-1/2 -translate-y-1/2 rounded-full border border-primary/25 [animation-delay:180ms]"
                />
                <div
                    v-for="burst in 3"
                    :key="burst"
                    data-test="match-firework-burst"
                    class="motion-match-firework absolute size-2"
                    :class="[
                        burst === 1 && 'top-[58%] left-[18%]',
                        burst === 2 && 'top-[24%] left-[70%]',
                        burst === 3 && 'top-[72%] left-[78%]',
                    ]"
                    :style="{ animationDelay: `${(burst - 1) * 240}ms` }"
                >
                    <span
                        v-for="ray in 12"
                        :key="ray"
                        class="motion-match-ray absolute bottom-0 left-1/2 h-20 w-px origin-bottom bg-gradient-to-t from-amber-200 via-amber-300/80 to-transparent"
                        :style="{
                            transform: `rotate(${ray * 30}deg)`,
                            animationDelay: `${(burst - 1) * 240 + ray * 18}ms`,
                        }"
                    />
                </div>
                <span
                    v-for="particle in 18"
                    :key="particle"
                    class="motion-match-jewel absolute size-1.5 rotate-45 rounded-[1px] bg-amber-200 shadow-[0_0_14px_rgba(252,211,77,.9)]"
                    :style="{
                        left: `${8 + ((particle * 37) % 84)}%`,
                        top: `${10 + ((particle * 23) % 78)}%`,
                        animationDelay: `${160 + particle * 30}ms`,
                    }"
                />
            </div>
        </div>
    </Teleport>
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
