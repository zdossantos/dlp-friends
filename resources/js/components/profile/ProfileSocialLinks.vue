<script setup lang="ts">
import facebook from '@/components/profile/social-icons/facebook.svg';
import instagram from '@/components/profile/social-icons/instagram.svg';
import tiktok from '@/components/profile/social-icons/tiktok.svg';
import x from '@/components/profile/social-icons/x.svg';
import youtube from '@/components/profile/social-icons/youtube.svg';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { SocialLink } from '@/types';

defineProps<{ links: SocialLink[] }>();
const { t } = useTranslations();
const icons = { instagram, facebook, tiktok, youtube, x };
</script>

<template>
    <section v-if="links.length" class="space-y-2">
        <h2 class="text-sm font-semibold">
            {{ t('profile.social_links.title') }}
        </h2>
        <p class="text-xs text-muted-foreground">
            {{ t('profile.social_links.external') }}
        </p>
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="link in links"
                :key="link.network"
                as-child
                variant="secondary"
                class="min-h-11 rounded-full px-3"
            >
                <a
                    :href="link.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :data-test="`social-link-${link.network}`"
                    :aria-label="
                        t('profile.social_links.open', {
                            network: t(
                                `profile.social_links.networks.${link.network}`,
                            ),
                        })
                    "
                >
                    <span
                        aria-hidden="true"
                        class="size-4 shrink-0 bg-current"
                        :style="{
                            maskImage: `url(${icons[link.network]})`,
                            maskSize: 'contain',
                            maskRepeat: 'no-repeat',
                        }"
                    />
                    {{ t(`profile.social_links.networks.${link.network}`) }}
                </a>
            </Button>
        </div>
    </section>
</template>
