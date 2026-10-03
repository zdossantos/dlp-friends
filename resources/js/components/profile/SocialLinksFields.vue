<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type { SocialLink, SocialLinksVisibility, SocialNetwork } from '@/types';

const links = defineModel<SocialLink[]>('links', { required: true });
const visibility = defineModel<SocialLinksVisibility>('visibility', {
    required: true,
});
defineProps<{ errors: Record<string, string> }>();
const { t } = useTranslations();
const networks: SocialNetwork[] = [
    'instagram',
    'facebook',
    'tiktok',
    'youtube',
    'x',
];
const visibilities: SocialLinksVisibility[] = ['hidden', 'matches', 'members'];
function add(): void {
    const network = networks.find(
        (candidate) => !links.value.some((link) => link.network === candidate),
    );

    if (network && links.value.length < 3) {
        links.value.push({ network, url: '' });
    }
}
</script>

<template>
    <fieldset class="min-w-0 space-y-3 rounded-2xl border p-3">
        <legend class="px-1 font-semibold">
            {{ t('profile.social_links.title') }}
        </legend>
        <p class="text-sm text-muted-foreground">
            {{ t('profile.social_links.description') }}
        </p>
        <div
            v-for="(link, index) in links"
            :key="index"
            class="space-y-2 rounded-xl bg-muted/30 p-2"
        >
            <Label :for="`social-network-${index}`">{{
                t('profile.social_links.network', { number: index + 1 })
            }}</Label>
            <Select v-model="link.network">
                <SelectTrigger :id="`social-network-${index}`" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="network in networks"
                        :key="network"
                        :value="network"
                        :disabled="
                            links.some(
                                (other, otherIndex) =>
                                    otherIndex !== index &&
                                    other.network === network,
                            )
                        "
                    >
                        {{ t(`profile.social_links.networks.${network}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors[`social_links.${index}.network`]" />
            <Label :for="`social-url-${index}`">{{
                t('profile.social_links.url', { number: index + 1 })
            }}</Label>
            <Input
                :id="`social-url-${index}`"
                v-model="link.url"
                :name="`social_links[${index}][url]`"
                type="url"
                maxlength="2048"
                :placeholder="t('profile.social_links.placeholder')"
                :aria-invalid="!!errors[`social_links.${index}.url`]"
                :aria-describedby="
                    errors[`social_links.${index}.url`]
                        ? `social-url-error-${index}`
                        : undefined
                "
            />
            <InputError
                :id="`social-url-error-${index}`"
                :message="errors[`social_links.${index}.url`]"
            />
            <Button
                type="button"
                variant="ghost"
                :data-test="`remove-social-link-${index}`"
                @click="links.splice(index, 1)"
                >{{
                    t('profile.social_links.remove', { number: index + 1 })
                }}</Button
            >
        </div>
        <InputError :message="errors.social_links" />
        <Button
            v-if="links.length < 3"
            type="button"
            variant="outline"
            data-test="add-social-link"
            @click="add"
            >{{ t('profile.social_links.add') }}</Button
        >
        <div class="grid gap-2">
            <Label for="social-links-visibility">{{
                t('profile.social_links.visibility')
            }}</Label>
            <Select v-model="visibility">
                <SelectTrigger id="social-links-visibility" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in visibilities"
                        :key="option"
                        :value="option"
                        >{{ t(`profile.social_links.${option}`) }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="errors.social_links_visibility" />
            <p
                v-if="visibility === 'members'"
                class="text-sm text-muted-foreground"
            >
                {{ t('profile.social_links.warning') }}
            </p>
        </div>
    </fieldset>
</template>
