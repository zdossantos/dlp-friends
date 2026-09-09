@props(['spa' => false])

@if (config('services.google.analytics_id'))
    <div
        data-analytics-consent
        data-analytics-measurement-id="{{ config('services.google.analytics_id') }}"
        data-analytics-spa="{{ $spa ? 'true' : 'false' }}"
    >
        <section
            hidden
            role="region"
            aria-labelledby="analytics-consent-title"
            aria-describedby="analytics-consent-description"
            data-test="analytics-consent-dialog"
            class="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-2xl rounded-3xl border bg-card p-5 text-card-foreground shadow-2xl sm:bottom-6 sm:p-6"
        >
            <h2 id="analytics-consent-title" class="font-accent text-lg font-bold">{{ __('analytics.consent.title') }}</h2>
            <p id="analytics-consent-description" class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ __('analytics.consent.description') }}
                <a class="font-medium underline underline-offset-4" href="{{ \App\Support\PublicUrls::privacyPath(app()->getLocale()) }}">{{ __('analytics.consent.learn_more') }}</a>
            </p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <button type="button" data-analytics-accept class="inline-flex min-h-12 items-center justify-center rounded-2xl border-2 border-primary bg-background px-5 font-semibold text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                    {{ __('analytics.consent.accept') }}
                </button>
                <button type="button" data-analytics-refuse class="inline-flex min-h-12 items-center justify-center rounded-2xl border-2 border-primary bg-background px-5 font-semibold text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                    {{ __('analytics.consent.refuse') }}
                </button>
            </div>
        </section>

        <button
            type="button"
            data-analytics-settings
            data-test="analytics-consent-settings"
            aria-expanded="false"
            class="fixed right-4 bottom-4 z-40 inline-flex min-h-11 items-center justify-center rounded-2xl border bg-card px-4 text-sm font-semibold text-card-foreground shadow-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            {{ __('analytics.consent.settings') }}
        </button>
    </div>
    @vite('resources/js/analyticsConsent.ts')
@endif
