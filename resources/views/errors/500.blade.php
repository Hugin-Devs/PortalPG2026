@include('errors._standalone', [
    'code' => 500,
    'title' => 'Error interno del servidor',
    'message' => 'Ocurrió un problema al procesar tu solicitud. El error ya quedó registrado y el equipo técnico puede revisarlo. Intenta de nuevo en unos minutos.',
])
