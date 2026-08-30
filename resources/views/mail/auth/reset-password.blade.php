<x-mail::message>
# {{ __('account.mail.password_reset.heading') }}

{{ __('account.mail.password_reset.intro') }}

<x-mail::button :url="$resetUrl">
{{ __('account.mail.password_reset.action') }}
</x-mail::button>

{{ __('account.mail.password_reset.expires', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]) }}

{{ __('account.mail.password_reset.ignore') }}

<x-slot:subcopy>
{{ __('common.mail.fallback_link') }}

<span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
