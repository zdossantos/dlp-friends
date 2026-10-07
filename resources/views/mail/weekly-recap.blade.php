<x-mail::message>
# {{ __('weekly-recap.heading') }}

{{ trans_choice('weekly-recap.conversations', $conversations, ['count' => $conversations]) }}

@if ($matches !== null)
{{ trans_choice('weekly-recap.matches', $matches, ['count' => $matches]) }}
@endif

<x-mail::button :url="url('/app')">
{{ __('weekly-recap.action') }}
</x-mail::button>

{{ __('weekly-recap.privacy') }}

[{{ __('weekly-recap.settings') }}]({{ route('notification-preferences.edit') }})
</x-mail::message>
