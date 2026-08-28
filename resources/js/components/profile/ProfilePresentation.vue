<script setup lang="ts">
import { CalendarDays, Sparkles } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    avatar: {
        name: string;
        image_url: string;
        primary_color: string;
        secondary_color: string;
    };
    displayName: string;
    ageLabel: string;
    bio: string;
    visitFrequency: string;
    interests: Array<{ id: number; name: string }>;
    aboutLabel: string;
    interestsLabel: string;
    visitFrequencyLabel: string;
}>();
</script>

<template>
    <section
        data-test="profile-presentation"
        class="flex max-h-full w-full flex-col overflow-hidden rounded-[2rem] border border-border/70 bg-card shadow-xl shadow-primary/10"
    >
        <div
            data-test="profile-presentation-hero"
            class="relative flex h-[clamp(11rem,24svh,14rem)] shrink-0 items-end justify-center overflow-hidden px-6 pt-4"
            :style="{
                backgroundImage: `linear-gradient(145deg, ${avatar.primary_color}, ${avatar.secondary_color})`,
            }"
        >
            <div
                data-test="profile-avatar-hero"
                class="absolute inset-0 flex items-end justify-center px-6 pt-4"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(255,255,255,.5),transparent_35%),radial-gradient(circle_at_15%_70%,rgba(255,255,255,.22),transparent_34%)]"
                />
                <img
                    :src="avatar.image_url"
                    :alt="`Avatar ${avatar.name}`"
                    class="relative z-20 h-[calc(100%-0.75rem)] w-full object-contain drop-shadow-2xl"
                    data-test="profile-avatar"
                />
            </div>
            <div class="absolute top-3 right-3 z-30 flex gap-2">
                <slot name="hero-actions" />
            </div>
        </div>

        <div
            data-test="profile-information-sheet"
            class="relative z-20 -mt-5 min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain rounded-t-[2rem] bg-card p-4 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-3xl font-semibold tracking-tight">
                            {{ displayName }}
                        </h1>
                        <Badge
                            class="rounded-full px-3 py-1"
                            variant="secondary"
                        >
                            {{ ageLabel }}
                        </Badge>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <slot name="badges" />
                    </div>
                </div>
                <slot name="summary-actions" />
            </div>

            <section>
                <h2
                    data-test="profile-about-title"
                    class="mb-1 text-sm font-semibold"
                >
                    {{ aboutLabel }}
                </h2>
                <p class="leading-6 whitespace-pre-line text-muted-foreground">
                    {{ bio }}
                </p>
            </section>

            <section>
                <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold">
                    <CalendarDays
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    {{ visitFrequencyLabel }}
                </h2>
                <p class="text-muted-foreground">{{ visitFrequency }}</p>
            </section>

            <section v-if="interests.length > 0">
                <h2
                    data-test="profile-interests-title"
                    class="mb-2 flex items-center gap-2 text-sm font-semibold"
                >
                    <Sparkles class="size-4 text-primary" aria-hidden="true" />
                    {{ interestsLabel }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="interest in interests"
                        :key="interest.id"
                        variant="secondary"
                        class="rounded-full px-3 py-1.5"
                    >
                        {{ interest.name }}
                    </Badge>
                </div>
            </section>
        </div>
    </section>
</template>
