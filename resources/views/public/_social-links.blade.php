@php
    $socialMeta = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'fa-facebook-f'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-instagram'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'fa-youtube'],
        'x' => ['label' => 'X (Twitter)', 'icon' => 'fa-x-twitter'],
        'threads' => ['label' => 'Threads', 'icon' => 'fa-threads'],
        'telegram' => ['label' => 'Telegram', 'icon' => 'fa-telegram'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fa-whatsapp'],
    ];
    $socialValues = \App\Models\Setting::whereIn(
        'key',
        array_map(fn ($field) => 'social_' . $field, array_keys($socialMeta))
    )->pluck('value', 'key');
    $socialLinks = [];
    foreach ($socialMeta as $field => $meta) {
        $socialUrl = trim((string) ($socialValues['social_' . $field] ?? ''));
        if ($socialUrl !== '' && preg_match('#^https?://#i', $socialUrl)) {
            $socialLinks[$field] = $meta + ['url' => $socialUrl];
        }
    }
@endphp
@if(count($socialLinks))
<ul class="list-unstyled d-flex align-items-center gap-3 mb-0 {{ $ulClass ?? '' }}">
    @foreach($socialLinks as $socialLink)
        <li>
            <a href="{{ $socialLink['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $socialLink['label'] }}" aria-label="{{ $socialLink['label'] }}" class="fs-5 {{ $linkClass ?? 'text-primary' }}">
                <i class="fa-brands {{ $socialLink['icon'] }}"></i>
            </a>
        </li>
    @endforeach
</ul>
@endif
