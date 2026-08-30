<x-mail::message>
# {{ __('mail.verification.heading') }}

{{ __('mail.verification.intro') }}

<x-mail::button :url="$verificationUrl">
{{ __('mail.verification.action') }}
</x-mail::button>

{{ __('mail.verification.ignore') }}

<x-slot:subcopy>
{{ __('mail.fallback_link') }}

<span class="break-all">[{{ $verificationUrl }}]({{ $verificationUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
