<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-google-tags />
        <title>{{ $document['meta']['title'] }}</title>
        <meta name="description" content="{{ $document['meta']['description'] }}">
        <link rel="canonical" href="{{ $canonical }}">
        @foreach ($alternates as $language => $href)
            <link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
        @endforeach
        <script>
            (function() {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
        <style>
            html { background-color: hsl(32 40% 98%); }
            html.dark { background-color: hsl(258 30% 8%); }
            @media print {
                [data-print-hidden] { display: none !important; }
                [data-test="legal-document-card"] { border: 0 !important; box-shadow: none !important; }
            }
        </style>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
        <link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="16x16 32x32 48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" type="image/png" sizes="180x180">
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <div class="relative min-h-svh overflow-hidden bg-background">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_38%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_42%)] opacity-60"></div>

            <div class="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8">
                <header data-print-hidden class="flex items-center justify-between gap-4 py-2">
                    <a href="{{ route('landing.show', ['locale' => $locale], false) }}" class="flex items-center gap-3 rounded-2xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:outline-none">
                        <span class="grid size-11 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20">
                            <span aria-hidden="true" data-test="app-logo-icon" class="inline-block size-7 shrink-0 bg-current [mask-image:url('/brand/dlp-friends-logo.svg')] [mask-size:contain] [mask-position:center] [mask-repeat:no-repeat] [-webkit-mask-image:url('/brand/dlp-friends-logo.svg')] [-webkit-mask-position:center] [-webkit-mask-repeat:no-repeat] [-webkit-mask-size:contain]"></span>
                        </span>
                        <span class="font-accent text-lg font-bold tracking-tight">{{ __('common.brand.name') }}</span>
                    </a>

                    <nav aria-labelledby="legal-language-label" class="flex items-center gap-1 rounded-xl border border-border/70 bg-card/90 p-1 shadow-sm backdrop-blur">
                        <span id="legal-language-label" class="sr-only">{{ __('common.locale.label') }}</span>
                        @foreach (['fr', 'en'] as $language)
                            <a href="{{ $navigationAlternates[$language] }}" hreflang="{{ $language }}" lang="{{ $language }}" @class([
                                'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                'bg-primary text-primary-foreground' => $locale === $language,
                                'text-muted-foreground hover:bg-muted hover:text-foreground' => $locale !== $language,
                            ])>{{ strtoupper($language) }}</a>
                        @endforeach
                    </nav>
                </header>

                <main id="contenu-principal" class="flex-1 py-10 sm:py-14 lg:py-16">
                    <header class="mx-auto max-w-3xl text-center">
                        <p class="mx-auto w-fit rounded-full bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground">{{ $document['date_label'] }} {{ $document['date'] }}</p>
                        <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $document['title'] }}</h1>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-muted-foreground">{{ $document['meta']['description'] }}</p>
                    </header>

                    <div class="mt-12 grid items-start gap-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-8">
                        <nav data-print-hidden class="rounded-3xl border border-border/70 bg-card/90 p-5 shadow-lg shadow-primary/5 backdrop-blur lg:sticky lg:top-6" aria-labelledby="legal-toc-label">
                            <strong id="legal-toc-label" class="text-sm font-semibold tracking-wide text-primary uppercase">{{ $document['toc_label'] }}</strong>
                            <ol class="mt-4 grid gap-1">
                                @foreach($document['sections'] as $section)
                                    <li><a href="#{{ $section['id'] }}" class="block rounded-xl px-3 py-2 text-sm leading-5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ $section['title'] }}</a></li>
                                @endforeach
                            </ol>
                        </nav>

                        <article data-test="legal-document-card" class="overflow-hidden rounded-[2rem] border border-border/70 bg-card/95 px-5 shadow-xl shadow-primary/5 backdrop-blur sm:px-8 lg:px-10">
                            @foreach($document['sections'] as $section)
                                <section id="{{ $section['id'] }}" class="scroll-mt-6 border-b border-border/70 py-8 last:border-b-0 sm:py-10">
                                    <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ $section['title'] }}</h2>
                                    <div class="mt-4 grid gap-4 text-[0.975rem] leading-7 text-muted-foreground">
                                        @foreach($section['paragraphs'] as $paragraph)
                                            <p>{{ str_replace([':email', ':backup_days'], [$contactEmail ?: $document['contact_missing'], config('legal.retention.backup_days')], $paragraph) }}</p>
                                        @endforeach
                                        @if($section['items'])
                                            <ul class="grid gap-3 pl-1">
                                                @foreach($section['items'] as $item)
                                                    <li class="flex gap-3"><span aria-hidden="true" class="mt-2.5 size-1.5 shrink-0 rounded-full bg-primary"></span><span>{{ $item }}</span></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </section>
                            @endforeach
                        </article>
                    </div>
                </main>

                <footer data-print-hidden class="border-t border-border/70 py-6 text-center text-xs leading-5 text-muted-foreground">{{ $document['footer'] }}</footer>
            </div>
        </div>
    </body>
</html>
