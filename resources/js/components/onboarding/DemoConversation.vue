<script setup lang="ts">
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

defineProps<{ displayName: string; locked?: boolean }>();
const emit = defineEmits<{ complete: [] }>();

const draft = ref('');
const sentMessage = ref<string | null>(null);

function send(): void {
    const message = draft.value.trim();

    if (!message) {
        return;
    }

    sentMessage.value = message;
    draft.value = '';
}
</script>

<template>
    <Card class="w-full max-w-md rounded-[2rem] sm:max-w-lg">
        <CardHeader>
            <CardTitle data-test="demo-conversation-heading" tabindex="-1">
                Conversation de démonstration
            </CardTitle>
            <p class="text-sm text-muted-foreground">
                Échange fictif avec {{ displayName }}
            </p>
        </CardHeader>
        <CardContent class="space-y-5">
            <div
                class="min-h-32 space-y-3 rounded-2xl bg-muted p-4"
                aria-live="polite"
            >
                <p
                    class="w-fit max-w-[85%] rounded-2xl bg-card px-4 py-2 text-sm"
                >
                    Bonjour ! Quel est ton endroit préféré dans le parc ?
                </p>
                <p
                    v-if="sentMessage"
                    class="ml-auto w-fit max-w-[85%] rounded-2xl bg-primary px-4 py-2 text-sm text-primary-foreground"
                >
                    {{ sentMessage }}
                </p>
            </div>
            <form class="flex gap-2" @submit.prevent="send">
                <Input
                    v-model="draft"
                    data-test="demo-message-input"
                    aria-label="Message de démonstration"
                    placeholder="Écrivez une réponse fictive"
                    :disabled="locked"
                />
                <Button
                    type="submit"
                    data-test="send-demo-message"
                    :disabled="locked || !draft.trim()"
                >
                    <Send aria-hidden="true" />
                    <span class="sr-only">Envoyer le message fictif</span>
                </Button>
            </form>
            <Button
                type="button"
                class="w-full"
                data-test="complete-demo-conversation"
                :disabled="locked || sentMessage === null"
                @click="emit('complete')"
            >
                Terminer le tutoriel
            </Button>
        </CardContent>
    </Card>
</template>
