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
            class="border-secondary-foreground/25 bg-secondary"
            :show-close-button="dismissible"
        >
            <DialogHeader>
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
            <DialogFooter>
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
