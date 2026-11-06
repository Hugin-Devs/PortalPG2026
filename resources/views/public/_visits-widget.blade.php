{{-- Contador de visitas al portal. Lo alimenta CountPortalVisit (§4.14):
     una visita por sesión de navegador y día, sin bots. Se incluye en el
     sidebar del home (desktop) y en el footer (solo móvil y solo home). --}}
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white fw-bold">VISITAS AL PORTAL</div>
    <div class="card-body text-center py-4">
        <i class="fa-solid fa-eye fa-2x text-primary mb-2"></i>
        <div class="fs-2 fw-bold text-primary">{{ number_format($visitsTotal ?? 0, 0, ',', '.') }}</div>
        <div class="small text-muted">visitantes únicos acumulados</div>
    </div>
</div>
