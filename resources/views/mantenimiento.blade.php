{{-- Sitio en mantenimiento para /extranet/* (intranet despublicada, §14.O).
     Standalone vía errors._standalone: sin layout, BD ni Vite. --}}
@include('errors._standalone', [
    'code' => 503,
    'title' => 'Sitio en mantenimiento',
    'message' => 'Estamos realizando trabajos de mantenimiento. El acceso a la extranet estará disponible próximamente. Puedes volver al inicio del portal.',
    'reload' => true,
])
