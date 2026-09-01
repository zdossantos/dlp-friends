<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['meta']['title'] }}</title>
    <meta name="description" content="{{ $document['meta']['description'] }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $language => $href)
        <link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
    @endforeach
    <style>
        :root{font-family:ui-sans-serif,system-ui;color:#26212b;background:#faf8fc}body{margin:0}a{color:#6941a5}a:focus-visible{outline:3px solid #8b5cf6;outline-offset:3px}.wrap{width:min(72rem,calc(100% - 2rem));margin:auto}.top{padding:1.25rem 0;border-bottom:1px solid #ddd}.hero{padding:3rem 0 1rem}.meta{color:#625b69}.grid{display:grid;grid-template-columns:minmax(12rem,18rem) 1fr;gap:3rem;padding:1rem 0 4rem}.toc{align-self:start;position:sticky;top:1rem}.toc a{display:block;padding:.35rem 0}.content{max-width:48rem;line-height:1.7}.content section{scroll-margin-top:1rem;margin-bottom:2.5rem}.content h2{line-height:1.25}.footer{border-top:1px solid #ddd;padding:1.5rem 0}@media(max-width:760px){.grid{grid-template-columns:1fr}.toc{position:static}}@media print{.top,.toc,.footer{display:none}.grid{display:block}.wrap{width:100%}a{color:inherit;text-decoration:none}}
    </style>
</head>
<body>
<header class="top">
    <div class="wrap">
        <a href="/{{ $locale }}">{{ __('common.brand.name') }}</a>
        <span aria-hidden="true">·</span>
        <a href="{{ $alternates[$locale === 'fr' ? 'en' : 'fr'] }}">{{ __('common.locale.'.($locale === 'fr' ? 'en' : 'fr')) }}</a>
    </div>
</header>
<main class="wrap">
    <div class="hero"><h1>{{ $document['title'] }}</h1><p class="meta">{{ $document['date_label'] }} {{ $document['date'] }}</p></div>
    <div class="grid">
        <nav class="toc" aria-labelledby="legal-toc-label">
            <strong id="legal-toc-label">{{ $document['toc_label'] }}</strong>
            @foreach($document['sections'] as $section)
                <a href="#{{ $section['id'] }}">{{ $section['title'] }}</a>
            @endforeach
        </nav>
        <article class="content">
            @foreach($document['sections'] as $section)
                <section id="{{ $section['id'] }}">
                    <h2>{{ $section['title'] }}</h2>
                    @foreach($section['paragraphs'] as $paragraph)
                        <p>{{ str_replace([':email', ':backup_days'], [$contactEmail ?: $document['contact_missing'], config('legal.retention.backup_days')], $paragraph) }}</p>
                    @endforeach
                    @if($section['items'])
                        <ul>
                            @foreach($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </article>
    </div>
</main>
<footer class="footer"><div class="wrap">{{ $document['footer'] }}</div></footer>
</body>
</html>
