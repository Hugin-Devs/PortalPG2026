@props(['path'])

@php
    $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : null;
    $webpUrl = $storagePath !== null && $storagePath !== '' ? \App\Services\Webp::url($storagePath) : null;
@endphp

@if($webpUrl)
<picture>
    <source srcset="{{ $webpUrl }}" type="image/webp">
    <img src="{{ asset($path) }}" {{ $attributes }}>
</picture>
@else
<img src="{{ asset($path) }}" {{ $attributes }}>
@endif
