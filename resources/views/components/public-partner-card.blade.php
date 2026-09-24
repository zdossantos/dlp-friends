@props(['partner', 'locale', 'activeSeasonalTheme' => null])

@php
    $revision = $partner->publishedRevision;
    $name = $locale === 'fr' ? $revision->name_fr : $revision->name_en;
    $description = $locale === 'fr' ? $revision->description_fr : $revision->description_en;
    $imageAttributes = new \Illuminate\View\ComponentAttributeBag([
        'alt' => __('common.welcome.partners.image_alt', ['name' => $name]),
    ]);
@endphp

<article data-test="public-partner-card" class="relative overflow-hidden rounded-3xl border border-border/70 bg-card/90 shadow-lg shadow-primary/5">
    <img
        src="{{ route('partner-profiles.image', $partner, false) }}"
        {{ $imageAttributes }}
        loading="lazy"
        class="aspect-video w-full object-cover"
    >
    <div class="space-y-2 p-6">
        <h3 class="font-accent text-xl font-bold tracking-tight">{{ $name }}</h3>
        <p class="text-sm leading-6 text-muted-foreground">{{ $description }}</p>
    </div>
    @if (($activeSeasonalTheme ?? null) === 'halloween')
        <x-seasonal-icon name="ghost" class="pointer-events-none absolute right-3 bottom-3 size-12 rotate-6 text-primary opacity-10" />
    @elseif (($activeSeasonalTheme ?? null) === 'christmas')
        <x-seasonal-icon name="gift" class="pointer-events-none absolute right-3 bottom-3 size-11 -rotate-6 text-primary opacity-[0.12]" />
    @endif
</article>
