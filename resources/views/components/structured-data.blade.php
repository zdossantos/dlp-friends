@props(['value'])

<script type="application/ld+json">{!! \Illuminate\Support\Js::encode($value, JSON_UNESCAPED_SLASHES) !!}</script>
