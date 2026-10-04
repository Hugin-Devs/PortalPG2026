<div class="container">
    <div class="text-center py-5">
        <h1 class="display-1 fw-bold text-primary">{{ $code }}</h1>
        <h2 class="h4 mb-3">{{ $title }}</h2>
        <p class="text-muted mx-auto mb-4" style="max-width: 560px;">{{ $message }}</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            @if($code == 419)
                <button type="button" class="btn btn-primary" onclick="window.location.reload()">Recargar e intentar</button>
            @endif
            @if($code == 401)
                <a href="{{ url('/admin/login') }}" class="btn btn-primary">Iniciar sesión</a>
            @endif
            <button type="button" class="btn btn-outline-primary" onclick="if (history.length > 1) { history.back(); } else { window.location.href = '{{ url('/') }}'; }">Volver</button>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary">Ir al inicio</a>
            @if($code == 403 && auth()->check())
                <a href="{{ url('/admin') }}" class="btn btn-outline-dark">Ir al panel</a>
            @endif
        </div>
    </div>
</div>
