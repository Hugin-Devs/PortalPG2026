@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Identidad / Enlaces</h1>
    <p class="text-muted">Identidad del sitio y enlaces del menú "Intranet GDC" de la barra de navegación pública.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ url('/admin/configuracion') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header fw-bold">
                <i class="fa-solid fa-id-badge me-2"></i>Identidad del sitio
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="site_name" class="fw-bold">Nombre del sitio</label>
                    <input type="text" name="site_name" id="site_name" class="form-control @error('site_name') is-invalid @enderror" maxlength="100" required value="{{ old('site_name', $settings['site_name']) }}">
                    <div class="form-text">Se usa como título del navegador en el sitio público y en el panel.</div>
                    @error('site_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-0">
                    <label for="logo" class="fw-bold">Logo</label>
                    <div class="mb-2">
                        <img src="{{ $logoUrl }}" alt="Logo actual" height="60" class="bg-white rounded p-1 border">
                        @unless($hasCustomLogo)
                            <span class="ms-2 text-muted small">Logo por defecto</span>
                        @endunless
                    </div>
                    <input type="file" name="logo" id="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
                    <div class="form-text">PNG, JPG, GIF, SVG o WebP · máx. 2 MB — deja vacío para mantener el actual</div>
                    @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @if($hasCustomLogo)
                        <div class="form-check mt-2">
                            <input type="checkbox" name="remove_logo" value="1" class="form-check-input" id="removeLogo">
                            <label class="form-check-label" for="removeLogo">Quitar logo actual (vuelve al por defecto)</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-bold">
                <i class="fa-solid fa-link me-2"></i>Enlaces de "Intranet GDC" (navbar pública)
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Cada destino puede ser una <strong>URL absoluta</strong> (<code>https://...</code>) o una
                    <strong>ruta dentro del servidor</strong> que empiece por <code>/</code> y exista en la raíz pública
                    (<code>public/</code>), p. ej. <code>/documentos/convocatorias.pdf</code>.
                    Si un campo queda <strong>vacío, el ítem se oculta</strong> del menú. Los enlaces abren en pestaña nueva.
                </p>

                <div class="mb-3">
                    <label for="nav_funcionarios_url" class="fw-bold">Funcionarios Activos</label>
                    <input type="text" name="nav_funcionarios_url" id="nav_funcionarios_url" class="form-control @error('nav_funcionarios_url') is-invalid @enderror" maxlength="255" placeholder="https://www.gdc.gob.ve/extranet/ o /documentos/..." value="{{ old('nav_funcionarios_url', $settings['nav_funcionarios_url']) }}">
                    @error('nav_funcionarios_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-0">
                    <label for="nav_jubilados_url" class="fw-bold">Jubilados</label>
                    <input type="text" name="nav_jubilados_url" id="nav_jubilados_url" class="form-control @error('nav_jubilados_url') is-invalid @enderror" maxlength="255" placeholder="https://... o /documentos/... (vacío = ocultar)" value="{{ old('nav_jubilados_url', $settings['nav_jubilados_url']) }}">
                    @error('nav_jubilados_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
@endsection
