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

    // Una URL por línea: cada línea válida genera un ícono (multi-cuenta por red).
    $socialLinks = [];
    foreach ($socialMeta as $field => $meta) {
        $value = (string) ($socialValues['social_' . $field] ?? '');
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: []), fn ($l) => $l !== ''));
        $valid = array_values(array_filter($lines, fn ($l) => preg_match('#^https?://#i', $l)));
        foreach ($valid as $line) {
            $socialLinks[] = $meta + ['url' => $line, 'total' => count($valid)];
        }
    }
@endphp
@if(count($socialLinks))
<ul class="list-unstyled d-flex align-items-center gap-3 mb-0 {{ $ulClass ?? '' }}">
    @foreach($socialLinks as $socialLink)
        @php
            // Con varias cuentas de la misma red, el tooltip incluye la URL para distinguirlas.
            $name = $socialLink['label'] . ($socialLink['total'] > 1 ? ' — ' . $socialLink['url'] : '');
        @endphp
        <li>
            <a href="{{ $socialLink['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $name }}" aria-label="{{ $name }}" class="fs-5 {{ $linkClass ?? 'text-primary' }}">
                <i class="fa-brands {{ $socialLink['icon'] }}"></i>
            </a>
        </li>
    @endforeach
</ul>
@endif
