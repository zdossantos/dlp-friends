<x-mail::message>
# {{ __('administration.members.mail.heading', ['name' => $displayName]) }}

{{ __('administration.members.mail.body') }}

{{ __('administration.members.mail.contact', ['email' => config('mail.from.address')]) }}
</x-mail::message>
