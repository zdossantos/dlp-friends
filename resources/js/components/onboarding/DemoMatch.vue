<script setup lang="ts">
import { MessageCircle, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{ displayName: string; locked?: boolean }>();
defineEmits<{ openConversation: [] }>();
</script>

<template>
    <Card
        class="w-full max-w-md rounded-[2rem] border-amber-300 bg-amber-50 text-center sm:max-w-lg dark:border-amber-700 dark:bg-amber-950"
    >
        <CardHeader class="items-center">
            <Sparkles class="size-12 text-amber-600" aria-hidden="true" />
            <CardTitle data-test="demo-match-heading" tabindex="-1"
                >Match de démonstration</CardTitle
            >
        </CardHeader>
        <CardContent class="space-y-5">
            <p>
                {{ displayName }} a aussi indiqué vouloir faire connaissance
                amicalement. Vous pouvez maintenant discuter ensemble.
            </p>
            <Button
                type="button"
                data-test="open-demo-conversation"
                :disabled="locked"
                @click="$emit('openConversation')"
            >
                <MessageCircle aria-hidden="true" /> Ouvrir la conversation
            </Button>
        </CardContent>
    </Card>
</template>
