<!DOCTYPE html>
<html lang="{{ $seo['locale'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        @foreach ($seo['alternates'] as $alternateLocale => $alternateUrl)
            <link rel="alternate" hreflang="{{ str_replace('_', '-', $alternateLocale) }}" href="{{ $alternateUrl }}">
        @endforeach
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:locale" content="{{ $seo['locale'] === 'fr' ? 'fr_FR' : 'en_GB' }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => config('app.name'),
            'url' => $seo['canonical'],
            'description' => $seo['description'],
            'applicationCategory' => 'SocialNetworkingApplication',
            'inLanguage' => $seo['locale'],
            'isAccessibleForFree' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
        <link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="16x16 32x32 48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" type="image/png" sizes="180x180">
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="font-sans antialiased">
        <div class="relative min-h-svh overflow-hidden bg-background text-foreground">
            <div aria-hidden="true" class="landing-drift pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_44%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_40%)] opacity-55"></div>

            <div class="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8">
                <header class="flex flex-col items-stretch gap-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20">
                            <span aria-hidden="true" data-test="app-logo-icon" class="inline-block size-7 shrink-0 bg-current [mask-image:url('/brand/dlp-friends-logo.svg')] [mask-size:contain] [mask-position:center] [mask-repeat:no-repeat] [-webkit-mask-image:url('/brand/dlp-friends-logo.svg')] [-webkit-mask-position:center] [-webkit-mask-repeat:no-repeat] [-webkit-mask-size:contain]"></span>
                        </span>
                        <span class="font-accent text-lg font-bold tracking-tight">{{ __('common.brand.name') }}</span>
                    </div>
                    <nav data-test="locale-switcher" aria-labelledby="landing-language-label" class="flex items-center gap-1 self-end rounded-xl border bg-card p-1 sm:self-auto">
                        <span id="landing-language-label" class="sr-only">{{ __('common.locale.label') }}</span>
                        @foreach (['fr', 'en'] as $locale)
                            <a
                                href="{{ route('landing.show', ['locale' => $locale], false) }}"
                                hreflang="{{ $locale }}"
                                lang="{{ $locale }}"
                                data-test="locale-{{ $locale }}"
                                @class([
                                    'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    'bg-primary text-primary-foreground' => $seo['locale'] === $locale,
                                    'text-muted-foreground hover:bg-muted hover:text-foreground' => $seo['locale'] !== $locale,
                                ])
                            >{{ strtoupper($locale) }}</a>
                        @endforeach
                    </nav>
                </header>

                <main id="contenu-principal" class="flex flex-1 flex-col py-12 sm:py-16 lg:py-20">
                    <section class="landing-reveal mx-auto flex min-h-[62svh] w-full max-w-3xl flex-col justify-center text-center">
                        <p class="mx-auto mb-5 w-fit rounded-full bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground">{{ __('common.welcome.eyebrow') }}</p>
                        <h1 class="font-accent text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ __('common.welcome.title') }}</h1>
                        <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">{{ __('common.welcome.description') }}</p>
                        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            <a href="{{ route('register', absolute: false) }}" data-test="landing-register" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-primary px-6 font-medium text-primary-foreground shadow-lg shadow-primary/20 transition-transform hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">{{ __('common.welcome.create_account') }}</a>
                            <a href="{{ route('login', absolute: false) }}" data-test="landing-login" class="inline-flex min-h-12 items-center justify-center rounded-2xl border bg-card px-6 font-medium shadow-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">{{ __('common.welcome.login') }}</a>
                        </div>
                    </section>

                    @php($benefits = ['interests', 'discovery', 'conversations'])
                    <section class="landing-reveal mx-auto mt-14 grid w-full max-w-5xl gap-4 sm:grid-cols-3">
                        @foreach ($benefits as $benefit)
                            <article class="rounded-3xl border border-border/70 bg-card/90 p-6 shadow-lg shadow-primary/5 backdrop-blur">
                                <span aria-hidden="true" class="mb-4 grid size-11 place-items-center rounded-2xl bg-secondary text-xl text-primary">✦</span>
                                <h2 class="font-semibold">{{ __("common.welcome.benefits.{$benefit}.title") }}</h2>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ __("common.welcome.benefits.{$benefit}.description") }}</p>
                            </article>
                        @endforeach
                    </section>

                    <section class="mx-auto mt-24 w-full max-w-5xl">
                        <div class="grid items-center gap-8 rounded-[2rem] border border-border/70 bg-card/90 p-7 shadow-xl shadow-primary/5 sm:p-10 lg:grid-cols-[0.9fr_1.1fr]">
                            <div>
                                <p class="text-sm font-semibold tracking-wide text-primary uppercase">{{ __('common.welcome.algorithm_eyebrow') }}</p>
                                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance">{{ __('common.welcome.algorithm_title') }}</h2>
                            </div>
                            <div>
                                <p class="leading-7 text-muted-foreground">{{ __('common.welcome.algorithm_description') }}</p>
                                <p class="mt-4 rounded-2xl bg-secondary px-5 py-4 text-sm font-medium text-secondary-foreground">{{ __('common.welcome.algorithm_note') }}</p>
                            </div>
                        </div>
                    </section>

                    @php($steps = ['profile', 'explore', 'exchange'])
                    <section class="mx-auto mt-24 w-full max-w-5xl">
                        <h2 class="text-center text-3xl font-semibold tracking-tight">{{ __('common.welcome.steps_title') }}</h2>
                        <ol class="mt-10 grid gap-5 md:grid-cols-3">
                            @foreach ($steps as $step)
                                <li class="relative rounded-3xl border bg-card p-6">
                                    <span aria-hidden="true" class="grid size-10 place-items-center rounded-full bg-primary font-semibold text-primary-foreground">{{ $loop->iteration }}</span>
                                    <h3 class="mt-5 font-semibold">{{ __("common.welcome.steps.{$step}.title") }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ __("common.welcome.steps.{$step}.description") }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </section>

                    <section class="mx-auto mt-24 w-full max-w-4xl rounded-[2rem] bg-primary px-6 py-12 text-center text-primary-foreground shadow-xl shadow-primary/20 sm:px-12">
                        <h2 class="text-3xl font-semibold tracking-tight text-balance">{{ __('common.welcome.final_title') }}</h2>
                        <p class="mx-auto mt-4 max-w-xl text-primary-foreground/80">{{ __('common.welcome.final_description') }}</p>
                        <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                            <a href="{{ route('register', absolute: false) }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-background px-6 font-semibold text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">{{ __('common.welcome.create_account') }}</a>
                            <a href="{{ route('login', absolute: false) }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-primary-foreground/30 px-6 font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">{{ __('common.welcome.login') }}</a>
                        </div>
                    </section>

                    <p class="mx-auto mt-10 max-w-2xl text-center text-xs leading-5 text-muted-foreground">{{ __('common.brand.disclaimer') }}</p>
                    <footer class="mt-6 flex justify-center gap-4 text-xs text-muted-foreground">
                        <a data-test="legal-terms" class="underline underline-offset-4" href="{{ \App\Support\PublicUrls::termsPath($seo['locale']) }}">{{ __('common.legal.terms') }}</a>
                        <a data-test="legal-privacy" class="underline underline-offset-4" href="{{ \App\Support\PublicUrls::privacyPath($seo['locale']) }}">{{ __('common.legal.privacy') }}</a>
                    </footer>
                </main>
            </div>
        </div>
    </body>
</html>
