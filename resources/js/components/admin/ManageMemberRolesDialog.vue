<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Settings2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/admin/members/roles';
import type { RoleName } from '@/types/auth';

type ManageableRoleName = Exclude<RoleName, 'admin'>;

const props = defineProps<{
    memberId: number;
    displayName: string;
    roles: Array<{ name: RoleName }>;
}>();
const { t } = useTranslations();
const open = ref(false);
const form = useForm<{
    roles: ManageableRoleName[];
    confirmed: boolean;
}>({
    roles: selectedManageableRoles(),
    confirmed: false,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.roles = selectedManageableRoles();
    form.confirmed = false;
    form.clearErrors();
});

function selectedManageableRoles(): ManageableRoleName[] {
    return props.roles
        .map((role) => role.name)
        .filter(
            (role): role is ManageableRoleName =>
                role === 'user' || role === 'partner',
        );
}

function hasRole(role: ManageableRoleName): boolean {
    return form.roles.includes(role);
}

function updateRole(role: ManageableRoleName, checked: boolean): void {
    form.roles = checked
        ? Array.from(new Set([...form.roles, role]))
        : form.roles.filter((selected) => selected !== role);
}

function submit(): void {
    form.patch(update(props.memberId).url, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                size="sm"
                variant="outline"
                data-test="manage-member-roles-trigger"
            >
                <Settings2 class="size-4" aria-hidden="true" />
                {{ t('administration.members.manage_roles') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    t('administration.members.manage_roles_title', {
                        name: displayName,
                    })
                }}</DialogTitle>
                <DialogDescription>
                    {{ t('administration.members.manage_roles_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">
                        {{ t('administration.members.roles') }}
                    </legend>
                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox
                            :id="`member-role-user-${memberId}`"
                            :model-value="hasRole('user')"
                            @update:model-value="
                                updateRole('user', $event === true)
                            "
                        />
                        {{ t('administration.members.role_user') }}
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox
                            :id="`member-role-partner-${memberId}`"
                            :model-value="hasRole('partner')"
                            @update:model-value="
                                updateRole('partner', $event === true)
                            "
                        />
                        {{ t('administration.members.role_partner') }}
                    </label>
                    <label
                        class="flex items-center gap-3 text-sm text-muted-foreground"
                    >
                        <Checkbox
                            :model-value="
                                roles.some((role) => role.name === 'admin')
                            "
                            disabled
                        />
                        {{ t('administration.members.role_admin_read_only') }}
                    </label>
                    <InputError :message="form.errors.roles" />
                </fieldset>

                <div class="space-y-2">
                    <label class="flex items-start gap-3 text-sm">
                        <Checkbox
                            :id="`member-role-confirmed-${memberId}`"
                            v-model="form.confirmed"
                        />
                        <span>{{
                            t('administration.members.roles_confirm')
                        }}</span>
                    </label>
                    <InputError :message="form.errors.confirmed" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            :disabled="form.processing"
                        >
                            {{ t('common.actions.cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="confirm-member-roles"
                        class="min-h-11"
                        :disabled="form.processing || !form.confirmed"
                    >
                        {{
                            form.processing
                                ? t('administration.members.roles_updating')
                                : t('administration.members.roles_submit')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
