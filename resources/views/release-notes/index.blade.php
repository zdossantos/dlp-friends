<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-google-tags />
        <title>{{ $content['meta']['title'] }}</title>
        <meta name="description" content="{{ $content['meta']['description'] }}">
        <link rel="canonical" href="{{ $canonical }}">
        @foreach ($alternates as $language => $href)
            <link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
        @endforeach
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $content['meta']['title'] }}">
        <meta property="og:description" content="{{ $content['meta']['description'] }}">
        <meta property="og:url" content="{{ $canonical }}">
        <x-brand-head />
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <div class="min-h-svh bg-background">
            <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <a href="/{{ $locale }}" class="font-accent text-lg font-bold">{{ __('common.brand.name') }}</a>
                <nav aria-label="{{ __('common.locale.label') }}" class="flex gap-2">
                    @foreach ($navigationAlternates as $language => $href)
                        <a href="{{ $href }}" hreflang="{{ $language }}" lang="{{ $language }}" class="rounded-lg px-3 py-2 font-semibold {{ $language === $locale ? 'bg-primary text-primary-foreground' : 'bg-card' }}">{{ strtoupper($language) }}</a>
                    @endforeach
                </nav>
            </header>
            <main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 sm:py-16">
                <nav aria-label="{{ __('common.accessibility.breadcrumb') }}" class="mb-8 text-sm text-muted-foreground">
                    <a href="/{{ $locale }}" class="underline underline-offset-4">{{ $content['home'] }}</a>
                    <span aria-hidden="true"> / </span><span aria-current="page">{{ $content['title'] }}</span>
                </nav>
                <header class="max-w-3xl">
                    <p class="font-semibold text-primary">{{ $content['eyebrow'] }}</p>
                    <h1 class="mt-4 font-accent text-4xl font-bold tracking-tight text-balance sm:text-6xl">{{ $content['title'] }}</h1>
                    <p class="mt-6 text-lg leading-8 text-muted-foreground">{{ $content['intro'] }}</p>
                </header>
                <div class="mt-12 space-y-6">
                    @foreach ($items as $item)
                        <article class="rounded-3xl border border-border/70 bg-card p-6 shadow-sm sm:p-8">
                            <div class="flex flex-wrap items-baseline justify-between gap-3">
                                <h2 class="font-accent text-2xl font-bold">{{ trans('release-notes.version', ['version' => $item['version']]) }}</h2>
                                <time datetime="{{ $item['date'] }}" class="text-sm text-muted-foreground">{{ trans('release-notes.published_on', ['date' => $item['date']]) }}</time>
                            </div>
                            <ul class="mt-5 list-disc space-y-2 pl-5 leading-7 text-muted-foreground">
                                @foreach ($item['changes'] as $change)<li>{{ $change }}</li>@endforeach
                            </ul>
                            <a class="mt-6 inline-block font-semibold text-primary underline underline-offset-4" href="{{ $item['release_url'] }}" rel="noopener noreferrer">{{ $content['release_link'] }}</a>
                        </article>
                    @endforeach
                </div>
            </main>
        </div>
        <x-analytics-consent page-type="release-notes" :page-title="$content['meta']['title']" :locale="$locale" />
    </body>
</html>
