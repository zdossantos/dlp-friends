<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Candy,
    Ghost,
    Gift,
    MoonStar,
    Snowflake,
    Sparkles,
    TreePine,
} from '@lucide/vue';
import { computed } from 'vue';
import MatchCelebration from '@/components/discovery/MatchCelebration.vue';
import AvatarPortrait from '@/components/profile/AvatarPortrait.vue';
import { Button } from '@/components/ui/button';
import { useResponsiveModal } from '@/composables/useResponsiveModal';
import { useSeasonalTheme } from '@/composables/useSeasonalTheme';
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
const seasonalTheme = useSeasonalTheme();
const variant = computed(() => seasonalTheme.value.active ?? 'standard');

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
            data-test="match-theme-panel"
            :data-theme="variant"
            :class="[
                'z-[60] overflow-hidden border-secondary-foreground/25 bg-secondary p-0',
                { 'pb-7': !isDesktop },
            ]"
            v-bind="isDesktop ? { showCloseButton: dismissible } : {}"
        >
            <div
                aria-hidden="true"
                class="absolute inset-x-0 top-0 h-44 bg-gradient-to-b from-primary/20 via-primary/8 to-transparent"
            />

            <template v-if="variant === 'halloween'">
                <MoonStar
                    class="absolute top-5 left-5 size-9 rotate-[-12deg] text-primary/45"
                    :stroke-width="1.35"
                />
                <Ghost
                    data-test="match-theme-corner-ornament"
                    class="absolute right-[-0.6rem] bottom-16 size-28 rotate-[-8deg] text-primary/13"
                    :stroke-width="1.15"
                />
                <Candy
                    class="absolute bottom-28 left-5 size-7 rotate-[-18deg] text-primary/25"
                    :stroke-width="1.35"
                />
            </template>

            <template v-else-if="variant === 'christmas'">
                <Snowflake
                    class="absolute top-6 left-6 size-8 rotate-12 text-primary/45"
                    :stroke-width="1.25"
                />
                <TreePine
                    data-test="match-theme-corner-ornament"
                    class="absolute right-[-0.4rem] bottom-14 size-28 text-primary/14"
                    :stroke-width="1.1"
                />
                <Gift
                    class="absolute bottom-24 left-5 size-8 rotate-[-10deg] text-primary/25"
                    :stroke-width="1.35"
                />
            </template>

            <component
                :is="Modal.Header"
                class="relative z-10 items-center px-6 pt-7 text-center sm:px-8"
            >
                <div
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/25 bg-background/70 px-3 py-1 text-xs font-semibold tracking-[0.14em] text-primary uppercase shadow-sm backdrop-blur"
                >
                    <Ghost
                        v-if="variant === 'halloween'"
                        class="size-4"
                        :stroke-width="1.7"
                    />
                    <Gift
                        v-else-if="variant === 'christmas'"
                        class="size-4"
                        :stroke-width="1.7"
                    />
                    <Sparkles v-else class="size-4" :stroke-width="1.7" />
                    {{ t('discovery.match.title') }}
                </div>

                <component
                    :is="Modal.Title"
                    data-test="match-heading"
                    class="sr-only text-secondary-foreground outline-none"
                    tabindex="-1"
                >
                    {{ t('discovery.match.title') }}
                </component>

                <div class="flex flex-col items-center">
                    <div
                        data-test="match-theme-avatar-frame"
                        class="relative mb-4 grid size-28 place-items-center rounded-full bg-background/70 shadow-[0_18px_45px_-24px_hsl(var(--primary))] ring-1 ring-primary/20"
                    >
                        <span
                            v-if="variant !== 'standard'"
                            data-test="match-theme-avatar-orbit"
                            class="motion-match-halo absolute -inset-2 rounded-full border border-dashed border-primary/45"
                        />
                        <Snowflake
                            v-if="variant === 'christmas'"
                            class="absolute -top-2 -right-3 size-8 rotate-12 rounded-full bg-secondary p-1.5 text-primary shadow-sm"
                            :stroke-width="1.45"
                        />
                        <Ghost
                            v-else-if="variant === 'halloween'"
                            class="absolute -top-2 -right-3 size-8 rotate-6 rounded-full bg-secondary p-1.5 text-primary shadow-sm"
                            :stroke-width="1.45"
                        />
                        <AvatarPortrait
                            data-test="match-member-avatar"
                            :avatar="match.avatar"
                            class="size-24 shrink-0 rounded-full ring-4 ring-secondary"
                        />
                    </div>
                    <p
                        data-test="match-member-name"
                        class="text-2xl font-bold tracking-tight text-secondary-foreground"
                    >
                        {{ match.displayName }}
                    </p>
                </div>
                <component
                    :is="Modal.Description"
                    class="mx-auto max-w-sm text-pretty text-secondary-foreground/80"
                >
                    {{
                        t('discovery.match.description', {
                            name: match.displayName,
                        })
                    }}
                </component>
            </component>
            <component
                :is="Modal.Footer"
                class="relative z-10 mt-6 px-6 sm:px-8"
            >
                <Button v-if="conversationHref" as-child class="shadow-sm">
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
                    data-test="open-match-conversation"
                    :disabled="locked"
                    class="shadow-sm"
                    @click="emit('openConversation')"
                >
                    {{ t('discovery.match.open_conversation') }}
                </Button>
                <Button
                    v-if="showContinue"
                    type="button"
                    variant="ghost"
                    :aria-label="t('discovery.match.continue')"
                    @click="emit('update:open', false)"
                >
                    {{ t('discovery.match.continue') }}
                </Button>
            </component>
        </component>
    </component>
</template>
