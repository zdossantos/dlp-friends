<x-mail::message>
# {{ __('account.mail.verification.heading') }}

{{ __('account.mail.verification.intro') }}

<x-mail::button :url="$verificationUrl">
{{ __('account.mail.verification.action') }}
</x-mail::button>

{{ __('account.mail.verification.ignore') }}

<x-slot:subcopy>
{{ __('common.mail.fallback_link') }}

<x-mail::fallback-link :url="$verificationUrl" />
</x-slot:subcopy>
</x-mail::message>
