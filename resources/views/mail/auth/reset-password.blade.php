<x-mail::message>
# {{ __('mail.password_reset.heading') }}

{{ __('mail.password_reset.intro') }}

<x-mail::button :url="$resetUrl">
{{ __('mail.password_reset.action') }}
</x-mail::button>

{{ __('mail.password_reset.expires', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]) }}

{{ __('mail.password_reset.ignore') }}

<x-slot:subcopy>
{{ __('mail.fallback_link') }}

<span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
