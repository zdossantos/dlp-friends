<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { redirect } from '@/routes/social';

const { t } = useTranslations();
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <Button variant="outline" as-child>
            <Link
                :href="redirect('google')"
                data-test="social-google"
                class="w-full"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path
                        fill="#4285F4"
                        d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.91h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.4Z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 22c2.7 0 4.98-.9 6.64-2.42l-3.24-2.54c-.9.6-2.05.96-3.4.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M6.39 13.87A6 6 0 0 1 6.08 12c0-.65.11-1.28.31-1.87V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.62Z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 6c1.47 0 2.79.51 3.83 1.5l2.88-2.88A9.64 9.64 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.62C7.18 7.76 9.39 6 12 6Z"
                    />
                </svg>
                {{ t('account.social.google') }}
            </Link>
        </Button>

        <Button variant="outline" as-child>
            <Link
                :href="redirect('apple')"
                data-test="social-apple"
                class="w-full"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                    fill="currentColor"
                >
                    <path
                        d="M17.05 12.54c-.03-3.05 2.49-4.53 2.61-4.6a5.61 5.61 0 0 0-4.42-2.39c-1.86-.2-3.67 1.12-4.62 1.12-.97 0-2.43-1.1-4.01-1.07a5.84 5.84 0 0 0-4.92 3c-2.14 3.7-.54 9.14 1.5 12.13 1.02 1.46 2.2 3.08 3.78 3.02 1.54-.06 2.11-.97 3.97-.97 1.83 0 2.37.97 3.98.93 1.66-.02 2.7-1.47 3.68-2.95a12.1 12.1 0 0 0 1.68-3.42 5.27 5.27 0 0 1-3.23-4.8ZM14.03 3.58A5.36 5.36 0 0 0 15.26 0a5.46 5.46 0 0 0-3.53 1.7 5.1 5.1 0 0 0-1.27 3.44 4.5 4.5 0 0 0 3.57-1.56Z"
                    />
                </svg>
                {{ t('account.social.apple') }}
            </Link>
        </Button>
    </div>
</template>
