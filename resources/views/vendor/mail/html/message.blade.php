<x-mail::layout>
<div class="message-brand">
<a href="{{ config('app.url') }}">
<span class="brand-name">{{ __('common.brand.name') }}</span>
<span class="brand-tagline">{{ __('common.mail.tagline') }}</span>
</a>
</div>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
{{ __('common.mail.disclaimer') }}

{{ __('common.mail.copyright', ['year' => date('Y')]) }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
