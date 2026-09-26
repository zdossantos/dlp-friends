<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Moon, Sparkles, Sun } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { activate, deactivate, update } from '@/routes/admin/seasonal-themes';
import type {
    SeasonalThemeConfiguration,
    SeasonalThemeName,
} from '@/types/seasonalTheme';

const props = defineProps<{
    themes: SeasonalThemeConfiguration[];
    activeTheme: SeasonalThemeName | null;
    nextTransitionAt: string | null;
    timezone: string;
}>();

const { t } = useTranslations();

const themeLabel = (theme: SeasonalThemeName): string =>
    t(`administration.seasonal_themes.${theme}`);

const hasManualTheme = (): boolean =>
    props.themes.some((theme) => theme.is_manually_active);
</script>

<template>
    <Head :title="t('administration.seasonal_themes.title')" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <header>
            <p class="text-sm font-medium text-primary">
                {{ t('administration.title') }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ t('administration.seasonal_themes.title') }}
            </h1>
            <p class="mt-1 max-w-3xl text-muted-foreground">
                {{ t('administration.seasonal_themes.description') }}
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Sparkles class="size-5" aria-hidden="true" />
                    {{ t('administration.seasonal_themes.current_title') }}
                </CardTitle>
                <CardDescription>
                    {{
                        t('administration.seasonal_themes.timezone', {
                            timezone,
                        })
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-3">
                <Badge :variant="activeTheme ? 'default' : 'secondary'">
                    {{
                        activeTheme
                            ? themeLabel(activeTheme)
                            : t('administration.seasonal_themes.standard')
                    }}
                </Badge>
                <p v-if="activeTheme" class="text-sm text-muted-foreground">
                    {{
                        t(
                            hasManualTheme()
                                ? 'administration.seasonal_themes.manual_source'
                                : 'administration.seasonal_themes.scheduled_source',
                        )
                    }}
                </p>
                <Form
                    v-if="hasManualTheme()"
                    v-bind="deactivate.form()"
                    v-slot="{ processing }"
                    class="basis-full sm:ml-auto sm:basis-auto"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                        class="w-full"
                    >
                        {{
                            t(
                                'administration.seasonal_themes.deactivate_manual',
                            )
                        }}
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <p
            class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
        >
            {{ t('administration.seasonal_themes.manual_priority') }}
        </p>

        <section class="grid min-w-0 gap-5 xl:grid-cols-2">
            <Card
                v-for="theme in themes"
                :key="theme.theme"
                :data-test="`seasonal-theme-${theme.theme}`"
                class="min-w-0 overflow-hidden"
            >
                <CardHeader>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <CardTitle>{{ themeLabel(theme.theme) }}</CardTitle>
                        <Badge
                            v-if="activeTheme === theme.theme"
                            variant="default"
                        >
                            {{ t('administration.seasonal_themes.active_now') }}
                        </Badge>
                    </div>
                    <CardDescription>
                        {{
                            t(
                                theme.starts_at && theme.ends_at
                                    ? 'administration.seasonal_themes.scheduled'
                                    : 'administration.seasonal_themes.not_scheduled',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2" aria-hidden="true">
                        <div
                            :class="`seasonal-${theme.theme}`"
                            class="rounded-xl border bg-background p-4 text-foreground"
                        >
                            <Sun class="mb-5 size-5 text-primary" />
                            <p class="text-sm font-medium">
                                {{
                                    t(
                                        'administration.seasonal_themes.preview_light',
                                    )
                                }}
                            </p>
                            <div class="mt-2 h-2 rounded-full bg-primary" />
                        </div>
                        <div
                            :class="`dark seasonal-${theme.theme}`"
                            class="rounded-xl border bg-background p-4 text-foreground"
                        >
                            <Moon class="mb-5 size-5 text-primary" />
                            <p class="text-sm font-medium">
                                {{
                                    t(
                                        'administration.seasonal_themes.preview_dark',
                                    )
                                }}
                            </p>
                            <div class="mt-2 h-2 rounded-full bg-primary" />
                        </div>
                    </div>

                    <Form
                        v-bind="update.form(theme.theme)"
                        v-slot="{ errors, processing }"
                        class="grid gap-4"
                    >
                        <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                            <div class="min-w-0 space-y-2">
                                <Label :for="`${theme.theme}-starts-at`">
                                    {{
                                        t(
                                            'administration.seasonal_themes.starts_at',
                                        )
                                    }}
                                </Label>
                                <Input
                                    :id="`${theme.theme}-starts-at`"
                                    name="starts_at"
                                    type="datetime-local"
                                    :default-value="theme.starts_at ?? ''"
                                    class="min-w-0"
                                />
                                <InputError :message="errors.starts_at" />
                            </div>
                            <div class="min-w-0 space-y-2">
                                <Label :for="`${theme.theme}-ends-at`">
                                    {{
                                        t(
                                            'administration.seasonal_themes.ends_at',
                                        )
                                    }}
                                </Label>
                                <Input
                                    :id="`${theme.theme}-ends-at`"
                                    name="ends_at"
                                    type="datetime-local"
                                    :default-value="theme.ends_at ?? ''"
                                    class="min-w-0"
                                />
                                <InputError :message="errors.ends_at" />
                            </div>
                        </div>
                        <Button type="submit" :disabled="processing">
                            {{
                                t(
                                    'administration.seasonal_themes.save_schedule',
                                    { theme: themeLabel(theme.theme) },
                                )
                            }}
                        </Button>
                    </Form>

                    <Form
                        v-if="!theme.is_manually_active"
                        v-bind="activate.form(theme.theme)"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="processing"
                            class="w-full"
                        >
                            {{
                                t(
                                    'administration.seasonal_themes.activate_manually',
                                    { theme: themeLabel(theme.theme) },
                                )
                            }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
