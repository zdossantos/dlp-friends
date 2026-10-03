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
    <nav
        v-if="links.length"
        :aria-label="t('profile.social_links.title')"
        class="flex flex-col gap-1"
    >
        <Button
            v-for="link in links"
            :key="link.network"
            as-child
            variant="secondary"
            size="icon"
            class="size-11 rounded-full border border-white/50 bg-background/90 text-foreground shadow-lg backdrop-blur"
        >
            <a
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                :data-test="`social-link-${link.network}`"
                :title="
                    t('profile.social_links.open', {
                        network: t(
                            `profile.social_links.networks.${link.network}`,
                        ),
                    })
                "
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
                    class="size-5 shrink-0 bg-current"
                    :style="{
                        maskImage: `url(${icons[link.network]})`,
                        maskSize: 'contain',
                        maskRepeat: 'no-repeat',
                    }"
                />
            </a>
        </Button>
    </nav>
</template>
