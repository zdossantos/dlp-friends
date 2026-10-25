<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import NavigationSheetLink from '@/components/navigation/NavigationSheetLink.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import type { BottomNavigationItem } from '@/types';

const props = defineProps<{
    label: string;
    icon: LucideIcon;
    items: BottomNavigationItem[];
    testId: string;
}>();

const { isCurrentNavigationItem: isActive } = useCurrentUrl();
const { t } = useTranslations();

const hasActiveChild = computed(() => props.items.some(isActive));
</script>

<template>
    <Sheet>
        <SheetTrigger :as-child="true">
            <button
                type="button"
                :data-test="`${testId}-trigger`"
                :aria-label="label"
                :aria-current="hasActiveChild ? 'page' : undefined"
                class="relative grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    hasActiveChild
                        ? 'bg-secondary text-secondary-foreground'
                        : undefined
                "
            >
                <component :is="icon" class="size-6" aria-hidden="true" />
            </button>
        </SheetTrigger>
        <SheetContent
            side="bottom"
            class="rounded-t-3xl px-4 pt-2 [padding-bottom:max(1.5rem,env(safe-area-inset-bottom))]"
        >
            <SheetHeader class="px-0 text-left">
                <SheetTitle>{{ label }}</SheetTitle>
                <SheetDescription>
                    {{ t('common.grouped_navigation.description') }}
                </SheetDescription>
            </SheetHeader>
            <div class="grid gap-2">
                <NavigationSheetLink
                    v-for="item in items"
                    :key="item.label"
                    :item="item"
                    :active="isActive(item)"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
