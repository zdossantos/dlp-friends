@props(['spa' => false])

@if (config('services.google.site_verification'))
    <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
@endif

@if (config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode(config('services.google.analytics_id')) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        gtag('js', new Date());
        gtag('config', {{ Illuminate\Support\Js::from(config('services.google.analytics_id')) }}@if ($spa), { send_page_view: false }@endif);
    </script>
@endif
