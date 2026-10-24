<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { SheetClose } from '@/components/ui/sheet';
import type { BottomNavigationItem } from '@/types';

defineProps<{
    item: Pick<BottomNavigationItem, 'href' | 'label' | 'icon' | 'testId'>;
    active: boolean;
}>();
</script>

<template>
    <SheetClose :as-child="true">
        <Link
            :href="item.href"
            :data-test="item.testId"
            :aria-label="item.label"
            :aria-current="active ? 'page' : undefined"
            class="flex min-h-12 items-center gap-3 rounded-2xl border border-border p-4 transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <component
                :is="item.icon"
                class="size-5 shrink-0"
                aria-hidden="true"
            />
            <span class="flex-1 font-medium">{{ item.label }}</span>
            <Check
                v-if="active"
                class="size-5 text-primary"
                aria-hidden="true"
            />
        </Link>
    </SheetClose>
</template>
