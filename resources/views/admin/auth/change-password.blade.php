<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $site = \App\Models\Setting::values();
        $siteTitle = $site['site_name'] !== '' ? $site['site_name'] : 'Portal GDC';
        $siteLogo = $site['site_logo'] !== '' ? 'storage/' . $site['site_logo'] : 'images/logo.png';
    @endphp
    <title>Cambiar contraseña - {{ $siteTitle }}</title>
    @vite(['resources/css/app.scss'])
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <x-img path="{{ $siteLogo }}" alt="Logo GDC" height="80" />
                    <h4 class="mt-2 text-primary">{{ $siteTitle }}</h4>
                    <p class="text-muted">Cambio de contraseña obligatorio</p>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        @if(session('warning'))
                            <div class="alert alert-warning">{{ session('warning') }}</div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif
                        <form method="POST" action="{{ route('password.change.update') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Contraseña actual</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                                <div class="form-text">Mínimo 8 caracteres.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Actualizar contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
