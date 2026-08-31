<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';

const props = withDefaults(
    defineProps<{
        open: boolean;
        match: { displayName: string };
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

function updateOpen(open: boolean): void {
    if (open || props.dismissible) {
        emit('update:open', open);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent
            class="overflow-hidden border-secondary-foreground/25 bg-secondary"
            :show-close-button="dismissible"
        >
            <div
                data-test="match-magic"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0"
            >
                <span
                    class="motion-match-halo absolute top-1/2 left-1/2 size-44 -translate-x-1/2 -translate-y-1/2 rounded-full border border-secondary-foreground/20"
                />
                <span
                    class="motion-match-halo absolute top-1/2 left-1/2 size-64 -translate-x-1/2 -translate-y-1/2 rounded-full border border-secondary-foreground/10 [animation-delay:80ms]"
                />
                <span
                    v-for="particle in 9"
                    :key="particle"
                    class="motion-magic-particle absolute top-1/2 left-1/2 size-2 rounded-full bg-amber-300 shadow-[0_0_18px_rgba(252,211,77,0.85)]"
                    :style="{
                        '--particle-x': `${(particle - 5) * 24}px`,
                        '--particle-y': `${-52 - Math.abs(particle - 5) * 12}px`,
                        animationDelay: `${particle * 32}ms`,
                    }"
                />
            </div>
            <DialogHeader class="relative z-10">
                <DialogTitle
                    data-test="match-heading"
                    class="text-secondary-foreground outline-none"
                    tabindex="-1"
                >
                    {{ t('discovery.match.title') }}
                </DialogTitle>
                <DialogDescription class="text-secondary-foreground">
                    {{
                        t('discovery.match.description', {
                            name: match.displayName,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="relative z-10">
                <Button v-if="conversationHref" as-child variant="outline">
                    <Link
                        :href="conversationHref"
                        data-test="open-match-conversation"
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
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
