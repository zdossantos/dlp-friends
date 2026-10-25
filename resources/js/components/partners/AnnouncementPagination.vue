<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { PartnerAnnouncementPagination } from '@/types';

defineProps<{ links: PartnerAnnouncementPagination }>();
const { t } = useTranslations();
</script>

<template>
    <nav
        v-if="links.previous || links.next"
        :aria-label="t('partners.pagination.label')"
        class="flex flex-wrap gap-3"
    >
        <Button v-if="links.previous" as-child variant="outline">
            <Link :href="links.previous" data-test="announcements-previous">
                {{ t('partners.pagination.previous') }}
            </Link>
        </Button>
        <Button v-if="links.next" as-child variant="outline">
            <Link :href="links.next" data-test="announcements-next">
                {{ t('partners.pagination.next') }}
            </Link>
        </Button>
    </nav>
</template>
