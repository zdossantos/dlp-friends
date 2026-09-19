<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Megaphone } from '@lucide/vue';
import PartnerAnnouncementForm from '@/components/partners/PartnerAnnouncementForm.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { index } from '@/routes/partner/announcements';
import type { PartnerAnnouncement } from '@/types';

defineProps<{ announcement: PartnerAnnouncement | null }>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('partners.announcements.form.page_title')" />

    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:py-10">
        <header class="space-y-4">
            <Button as-child variant="ghost" class="min-h-11">
                <Link :href="index().url">
                    <ArrowLeft aria-hidden="true" />
                    {{ t('partners.announcements.form.back') }}
                </Link>
            </Button>
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-primary/10 p-2 text-primary">
                    <Megaphone class="size-6" aria-hidden="true" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{
                        announcement
                            ? t('partners.announcements.form.edit_title')
                            : t('partners.announcements.form.create_title')
                    }}
                </h1>
            </div>
        </header>

        <PartnerAnnouncementForm :announcement="announcement" />
    </main>
</template>
