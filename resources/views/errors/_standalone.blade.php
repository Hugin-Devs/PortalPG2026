<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error {{ $code }} - Portal GDC</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: #f4f6fb; color: #212529; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .error-card { max-width: 560px; width: 100%; padding: 48px 32px; text-align: center; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(39, 51, 118, 0.12); }
        .error-code { margin: 0 0 8px; font-size: 88px; font-weight: 800; line-height: 1; color: #273376; }
        .error-title { margin: 0 0 12px; font-size: 22px; color: #111846; }
        .error-message { margin: 0 0 24px; line-height: 1.6; color: #6b7280; }
        .error-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 20px; border: 1px solid #273376; border-radius: 6px; font-size: 15px; font-family: inherit; text-decoration: none; cursor: pointer; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.88; }
        .btn-primary { background: #273376; color: #fff; }
        .btn-outline { background: #fff; color: #273376; }
        .brand { margin-top: 28px; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="error-card">
        <p class="error-code">{{ $code }}</p>
        <h1 class="error-title">{{ $title }}</h1>
        <p class="error-message">{{ $message }}</p>
        <div class="error-actions">
            @if($reload ?? false)
                <button type="button" class="btn btn-primary" data-action="reload">Reintentar</button>
            @endif
            <button type="button" class="btn btn-primary" data-action="back" data-fallback="/">Volver</button>
            <a class="btn btn-outline" href="/">Ir al inicio</a>
        </div>
        <p class="brand">Gobierno de Distrito Capital</p>
    </div>
</body>
</html>
