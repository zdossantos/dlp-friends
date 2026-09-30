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
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $content['meta']['title'] }}">
        <meta property="og:description" content="{{ $content['meta']['description'] }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ $locale === 'fr' ? 'fr_FR' : 'en_GB' }}">
        <meta property="og:image" content="{{ asset('apple-touch-icon.png') }}">
        @php($webPageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $content['meta']['title'],
            'description' => $content['meta']['description'],
            'url' => $canonical,
            'inLanguage' => $locale,
            'isAccessibleForFree' => true,
        ])
        @php($breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('guides.common.brand_home'), 'item' => \App\Support\PublicUrls::landing($locale)],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $content['title'], 'item' => $canonical],
            ],
        ])
        <x-structured-data :value="$webPageSchema" />
        <x-structured-data :value="$breadcrumbSchema" />
        <x-brand-head />
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <div class="min-h-svh overflow-hidden bg-background">
            <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <a href="/{{ $locale }}" class="font-accent text-lg font-bold">{{ __('common.brand.name') }}</a>
                <nav aria-label="Language" class="flex gap-2">
                    @foreach ($navigationAlternates as $language => $href)
                        <a href="{{ $href }}" hreflang="{{ $language }}" lang="{{ $language }}" class="rounded-lg px-3 py-2 font-semibold {{ $language === $locale ? 'bg-primary text-primary-foreground' : 'bg-card' }}">{{ strtoupper($language) }}</a>
                    @endforeach
                </nav>
            </header>

            <main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 sm:py-16">
                <nav aria-label="Breadcrumb" class="mb-8 text-sm text-muted-foreground">
                    <a href="/{{ $locale }}" class="underline underline-offset-4">{{ __('guides.common.brand_home') }}</a>
                    <span aria-hidden="true"> / </span>
                    <a href="{{ $canonical }}" aria-current="page">{{ $content['title'] }}</a>
                </nav>

                <article>
                    <header class="max-w-3xl">
                        <p class="font-semibold text-primary">{{ $content['eyebrow'] }}</p>
                        <h1 class="mt-4 font-accent text-4xl font-bold tracking-tight text-balance sm:text-6xl">{{ $content['title'] }}</h1>
                        <p class="mt-6 text-lg leading-8 text-muted-foreground">{{ $content['intro'] }}</p>
                    </header>

                    <div class="mt-12 grid gap-6 md:grid-cols-3">
                        @foreach ($content['sections'] as $section)
                            <section class="rounded-3xl border border-border/70 bg-card p-6 shadow-sm">
                                <h2 class="font-accent text-xl font-bold">{{ $section['title'] }}</h2>
                                <p class="mt-3 leading-7 text-muted-foreground">{{ $section['body'] }}</p>
                            </section>
                        @endforeach
                    </div>

                    <aside class="mt-12 rounded-3xl bg-secondary p-7 text-secondary-foreground">
                        <p class="leading-7">{{ __('guides.common.independence') }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('register', absolute: false) }}" class="rounded-2xl bg-primary px-5 py-3 font-semibold text-primary-foreground">{{ __('guides.common.cta') }}</a>
                            <a href="{{ \App\Support\PublicUrls::matchingPath($locale) }}" class="rounded-2xl border border-current px-5 py-3 font-semibold">{{ __('guides.common.matching') }}</a>
                        </div>
                    </aside>
                </article>
            </main>
        </div>
        <x-analytics-consent page-type="editorial_guide" :page-title="$content['meta']['title']" :locale="$locale" />
    </body>
</html>
