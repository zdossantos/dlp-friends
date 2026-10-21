<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('external-browser.title') }} — {{ __('common.brand.name') }}</title>
        <x-brand-head />
        @fonts
        @vite(['resources/css/app.css', 'resources/js/externalBrowser.ts'])
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <header class="mx-auto flex max-w-2xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <a href="/{{ $locale }}" class="rounded-sm font-accent text-lg font-bold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">{{ __('common.brand.name') }}</a>
            <nav aria-label="{{ __('common.locale.label') }}" class="flex gap-2">
                @foreach (['fr', 'en'] as $language)
                    <a href="{{ route('external-browser.localized', ['locale' => $language], false) }}" hreflang="{{ $language }}" lang="{{ $language }}" @if ($language === $locale) aria-current="page" @endif class="rounded-lg px-3 py-2 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring {{ $language === $locale ? 'bg-primary text-primary-foreground' : 'hover:bg-accent' }}">{{ strtoupper($language) }}</a>
                @endforeach
            </nav>
        </header>
        <main data-browser-help class="mx-auto max-w-2xl px-4 pt-8 pb-12 sm:px-6 sm:pt-12">
            <h1 class="text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">{{ __('external-browser.title') }}</h1>
            <p class="mt-4 text-lg leading-relaxed text-muted-foreground">{{ __('external-browser.intro') }}</p>

            <a href="{{ $destination }}" data-open-browser class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-center font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ __('external-browser.open') }}</a>

            <section aria-labelledby="instagram-help" class="mt-8">
                <h2 id="instagram-help" class="text-xl font-semibold">{{ __('external-browser.instagram_title') }}</h2>
                <p class="mt-3 leading-relaxed">{{ __('external-browser.instagram_intro') }}</p>
                <p class="mt-3 leading-relaxed">{{ __('external-browser.fallback') }}</p>
            </section>

            <div class="mt-6">
                <label for="browser-link" class="text-sm font-semibold">{{ __('external-browser.link_label') }}</label>
                <input id="browser-link" type="url" readonly value="{{ $destination }}" class="mt-2 min-h-12 w-full min-w-0 rounded-xl border border-input bg-card px-3 text-base select-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" aria-describedby="copy-status">
                <button type="button" hidden data-copy-link class="mt-3 min-h-12 w-full cursor-pointer rounded-xl bg-secondary px-4 py-3 font-semibold text-secondary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ __('external-browser.copy') }}</button>
                <p id="copy-status" role="status" aria-live="polite" class="mt-3 text-sm leading-relaxed" data-open-hint="{{ __('external-browser.open_hint') }}" data-copied="{{ __('external-browser.copied') }}" data-copy-failed="{{ __('external-browser.copy_failed') }}"></p>
            </div>

            <div class="mt-8 border-t border-border pt-6">
                <a href="/{{ $locale }}" data-continue class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-secondary px-4 py-3 text-center font-semibold text-secondary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ __('external-browser.continue') }}</a>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ __('external-browser.continue_hint') }}</p>
                <p class="mt-6 text-sm leading-relaxed text-muted-foreground">{{ __('external-browser.install_hint') }}</p>
            </div>
        </main>
    </body>
</html>
