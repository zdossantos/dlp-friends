<meta name="theme-color" media="(prefers-color-scheme: light)" content="hsl(32 40% 98%)">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="hsl(258 30% 8%)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
@php($brandPrefix = app()->environment('beta') ? '/beta' : '')
<meta name="apple-mobile-web-app-title" content="{{ app()->environment('beta') ? __('brand.beta_app_name') : config('app.name') }}">
<link rel="manifest" href="{{ $brandPrefix }}/app.webmanifest">
<link rel="icon" href="{{ $brandPrefix }}/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="{{ $brandPrefix }}/favicon.ico" type="image/x-icon" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="{{ $brandPrefix }}/apple-touch-icon.png" type="image/png" sizes="180x180">
