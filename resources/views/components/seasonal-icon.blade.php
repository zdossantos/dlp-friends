@props(['name'])

<svg
    {{ $attributes->merge([
        'aria-hidden' => 'true',
        'focusable' => 'false',
        'data-lucide-seasonal-icon' => $name,
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.5',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
    ]) }}
>
    @switch($name)
        @case('ghost')
            <path d="M15 10v1" /><path d="M7.528 20.472a1.6 1.6 0 0 1 2.277 0l1.057 1.056a1.6 1.6 0 0 0 2.276 0l1.057-1.056a1.6 1.6 0 0 1 2.277 0l1.114 1.114a1.4 1.4 0 0 0 2.414-1V10a8 8 0 0 0-16 0v10.586a1.4 1.4 0 0 0 2.414 1z" /><path d="M9 10v1" />
            @break
        @case('moon-star')
            <path d="M18 5h4" /><path d="M20 3v4" /><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401" />
            @break
        @case('gift')
            <path d="M12 7v14" /><path d="M20 11v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8" /><path d="M7.5 7a1 1 0 0 1 0-5A4.8 8 0 0 1 12 7a4.8 8 0 0 1 4.5-5 1 1 0 0 1 0 5" /><rect x="3" y="7" width="18" height="4" rx="1" />
            @break
        @case('tree-pine')
            <path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.7 1.7H17Z" /><path d="M12 22v-3" />
            @break
        @case('snowflake')
            <path d="m10 20-1.25-2.5L6 18" /><path d="M10 4 8.75 6.5 6 6" /><path d="m14 20 1.25-2.5L18 18" /><path d="m14 4 1.25 2.5L18 6" /><path d="m17 21-3-6h-4" /><path d="m17 3-3 6 1.5 3" /><path d="M2 12h6.5L10 9" /><path d="m20 10-1.5 2 1.5 2" /><path d="M22 12h-6.5L14 15" /><path d="m4 10 1.5 2L4 14" /><path d="m7 21 3-6-1.5-3" /><path d="m7 3 3 6h4" />
            @break
    @endswitch
</svg>
