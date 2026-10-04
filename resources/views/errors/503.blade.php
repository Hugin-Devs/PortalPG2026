@include('errors._standalone', [
    'code' => 503,
    'title' => 'Servicio no disponible',
    'message' => 'El sitio está en mantenimiento o recibe demasiadas solicitudes en este momento. Intenta de nuevo en unos minutos.',
    'reload' => true,
])
