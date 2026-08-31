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
                class="pointer-events-none absolute inset-0 overflow-hidden"
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
