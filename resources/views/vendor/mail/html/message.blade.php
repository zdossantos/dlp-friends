<x-mail::layout>
<div class="message-brand">
<a href="{{ config('app.url') }}">
<span class="brand-name">DLP Friends</span>
<span class="brand-tagline">{{ __('mail.brand.tagline') }}</span>
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
{{ __('mail.brand.disclaimer') }}

© {{ date('Y') }} DLP Friends
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
