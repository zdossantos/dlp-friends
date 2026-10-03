<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import {
    Sheet,
    SheetClose,
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

const { isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useTranslations();

function isActive(item: BottomNavigationItem): boolean {
    return (
        isCurrentOrParentUrl(item.href) ||
        item.activeParents?.some((parent) => isCurrentOrParentUrl(parent)) ===
            true
    );
}

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
                <SheetClose
                    v-for="item in items"
                    :key="item.label"
                    :as-child="true"
                >
                    <Link
                        :href="item.href"
                        :data-test="item.testId"
                        :aria-label="item.label"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        class="flex min-h-12 items-center gap-3 rounded-2xl border border-border p-4 transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <component
                            :is="item.icon"
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="flex-1 font-medium">{{ item.label }}</span>
                        <Check
                            v-if="isActive(item)"
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                    </Link>
                </SheetClose>
            </div>
        </SheetContent>
    </Sheet>
</template>
