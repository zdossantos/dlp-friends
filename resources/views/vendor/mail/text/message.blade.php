<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ __('common.brand.name') }} — {{ __('common.mail.tagline') }}
        </x-mail::header>
    </x-slot:header>

    {{ $slot }}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
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
