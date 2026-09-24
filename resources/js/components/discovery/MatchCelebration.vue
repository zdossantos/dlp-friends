<script setup lang="ts">
import {
    Candy,
    CandyCane,
    Ghost,
    Snowflake,
    Sparkles,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';
import { useSeasonalTheme } from '@/composables/useSeasonalTheme';

const seasonalTheme = useSeasonalTheme();
const variant = computed(() => seasonalTheme.value.active ?? 'standard');
</script>

<template>
    <Teleport to="body">
        <div
            data-test="match-celebration-layer"
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 z-[55] overflow-hidden"
        >
            <div
                v-if="variant === 'standard'"
                data-test="match-celebration-standard"
                class="absolute inset-0"
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

            <div
                v-else-if="variant === 'halloween'"
                data-test="match-celebration-halloween"
                class="absolute inset-0 text-primary"
            >
                <span
                    class="motion-match-halo absolute top-1/2 left-1/2 size-72 -translate-x-1/2 -translate-y-1/2 rounded-full border border-primary/35"
                />
                <Ghost
                    class="motion-match-seasonal absolute top-[32%] left-[7%] size-12 opacity-45"
                    :stroke-width="1.25"
                />
                <Candy
                    class="motion-match-seasonal absolute top-[27%] right-[8%] size-10 rotate-12 opacity-35 [animation-delay:320ms]"
                    :stroke-width="1.25"
                />
                <Sparkles
                    v-for="particle in 12"
                    :key="particle"
                    data-test="match-seasonal-particle"
                    class="motion-match-seasonal absolute size-5"
                    :style="{
                        left: `${8 + ((particle * 31) % 84)}%`,
                        top: `${9 + ((particle * 19) % 80)}%`,
                        animationDelay: `${particle * 70}ms`,
                    }"
                    :stroke-width="1.5"
                />
            </div>

            <div
                v-else
                data-test="match-celebration-christmas"
                class="absolute inset-0 text-primary"
            >
                <Star
                    class="motion-match-halo absolute top-1/2 left-1/2 size-56 -translate-x-1/2 -translate-y-1/2 opacity-30"
                    :stroke-width="1"
                />
                <CandyCane
                    class="motion-match-seasonal absolute top-[31%] right-[7%] size-12 opacity-40"
                    :stroke-width="1.25"
                />
                <Snowflake
                    v-for="particle in 14"
                    :key="particle"
                    data-test="match-seasonal-particle"
                    class="motion-match-seasonal absolute size-6"
                    :style="{
                        left: `${6 + ((particle * 29) % 88)}%`,
                        top: `${7 + ((particle * 23) % 84)}%`,
                        animationDelay: `${particle * 65}ms`,
                    }"
                    :stroke-width="1.25"
                />
            </div>
        </div>
    </Teleport>
</template>
