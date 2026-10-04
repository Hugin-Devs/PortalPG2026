@php
    $contactDefaults = [
        'address' => 'Av. Principal, Distrito Capital',
        'phone' => '+58 212-XXX-XXXX',
        'email' => 'contacto@gdc.gob.ve',
        'hours' => 'Lunes a viernes: 8:00 AM - 4:00 PM',
        'map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d283.27263825321114!2d-66.91497767711432!3d10.506585216453733!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8c2a5f4f6e625ba7%3A0x6e9963acb779ffdc!2sEdificio%20Toromaima%20-%20G.D.C.!5e0!3m2!1ses!2sve!4v1674657617845!5m2!1ses!2sve',
    ];
    $contactKeys = [
        'address' => 'contact_address',
        'phone' => 'contact_phone',
        'email' => 'contact_email',
        'hours' => 'contact_hours',
        'map_url' => 'contact_map_url',
    ];
    $contact = [];
    foreach ($contactKeys as $field => $key) {
        $value = \App\Models\Setting::where('key', $key)->first()?->value;
        $contact[$field] = ($value !== null && $value !== '') ? $value : $contactDefaults[$field];
    }
@endphp
<h4 class="text-primary">Información de contacto</h4>
<ul class="list-unstyled mt-3">
    <li class="mb-2"><strong>Dirección:</strong> {{ $contact['address'] }}</li>
    <li class="mb-2"><strong>Teléfono:</strong> {{ $contact['phone'] }}</li>
    <li class="mb-2"><strong>Email:</strong> {{ $contact['email'] }}</li>
</ul>
<div class="mt-4 p-3 bg-light rounded">
    <h5>Horario de atención</h5>
    <p class="mb-0">{{ $contact['hours'] }}</p>
</div>
<div class="mt-4">
    <h5>Ubicación</h5>
    <div class="ratio ratio-16x9">
        <iframe src="{{ $contact['map_url'] }}" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>
