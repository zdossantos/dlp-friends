<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            DLP Friends — {{ __('mail.brand.tagline') }}
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
            {{ __('mail.brand.disclaimer') }}

            © {{ date('Y') }} DLP Friends
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
