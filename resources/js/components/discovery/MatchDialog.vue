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

function updateOpen(open: boolean): void {
    if (open || props.dismissible) {
        emit('update:open', open);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent
            class="border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950"
            :show-close-button="dismissible"
        >
            <DialogHeader>
                <DialogTitle
                    data-test="match-heading"
                    class="text-amber-900 outline-none dark:text-amber-100"
                    tabindex="-1"
                >
                    C’est un match !
                </DialogTitle>
                <DialogDescription>
                    {{ match.displayName }} a aussi aimé votre profil. Vous
                    pouvez maintenant commencer à échanger.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button v-if="conversationHref" as-child variant="outline">
                    <Link
                        :href="conversationHref"
                        data-test="open-match-conversation"
                    >
                        Ouvrir la conversation
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
                    Ouvrir la conversation
                </Button>
                <Button
                    v-if="showContinue"
                    type="button"
                    aria-label="Continuer à découvrir"
                    @click="emit('update:open', false)"
                >
                    Continuer à découvrir
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
