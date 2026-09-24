@props(['partner', 'locale'])

@php
    $revision = $partner->publishedRevision;
    $name = $locale === 'fr' ? $revision->name_fr : $revision->name_en;
    $description = $locale === 'fr' ? $revision->description_fr : $revision->description_en;
    $imageAttributes = new \Illuminate\View\ComponentAttributeBag([
        'alt' => __('common.welcome.partners.image_alt', ['name' => $name]),
    ]);
@endphp

<article data-test="public-partner-card" class="overflow-hidden rounded-3xl border border-border/70 bg-card/90 shadow-lg shadow-primary/5">
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
</article>
