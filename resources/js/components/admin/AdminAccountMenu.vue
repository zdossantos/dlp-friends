<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const user = computed(() => page.props.auth.user);
const canAccessSettings = computed(() =>
    user.value.roles.some((role) => role.name === 'user'),
);
const identityLabel = computed(
    () => user.value.profile?.display_name || user.value.email,
);
const { getInitials } = useInitials();
const { t } = useTranslations();
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-11 rounded-full bg-card/95 p-1 shadow-md backdrop-blur"
                    data-test="admin-account-menu-trigger"
                    :aria-label="t('account.menu')"
                >
                    <Avatar class="size-8 overflow-hidden rounded-full">
                        <AvatarFallback
                            class="rounded-full bg-secondary font-semibold text-secondary-foreground"
                        >
                            {{ getInitials(identityLabel) }}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <UserMenuContent
                    :user="user"
                    :show-settings="canAccessSettings"
                />
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
