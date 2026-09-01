{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $locale => $url)
    <url>
        <loc>{{ $url }}</loc>
        <xhtml:link rel="alternate" hreflang="fr" href="{{ $urls['fr'] }}" />
        <xhtml:link rel="alternate" hreflang="en" href="{{ $urls['en'] }}" />
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $urls['fr'] }}" />
    </url>
@endforeach
</urlset>
