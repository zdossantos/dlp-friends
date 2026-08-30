<x-mail::message>
# {{ __('account.mail.verification.heading') }}

{{ __('account.mail.verification.intro') }}

<x-mail::button :url="$verificationUrl">
{{ __('account.mail.verification.action') }}
</x-mail::button>

{{ __('account.mail.verification.ignore') }}

<x-slot:subcopy>
{{ __('common.mail.fallback_link') }}

<span class="break-all">[{{ $verificationUrl }}]({{ $verificationUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
