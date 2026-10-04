@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Gaceta N° {{ $gaceta->numero }}</h1>
        <a href="{{ url('/admin/gacetas') }}" class="btn btn-secondary">Volver</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                @if(str_starts_with($gaceta->mime_type, 'image/'))
                    <img src="{{ asset('storage/' . $gaceta->path) }}" class="card-img-top" alt="Gaceta N° {{ $gaceta->numero }}" style="max-height: 350px; object-fit: contain;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                        <i class="fa-solid fa-file-pdf text-danger" style="font-size: 4rem;"></i>
                    </div>
                @endif
                <div class="card-body">
                    <small class="text-muted d-block text-truncate" title="{{ $gaceta->original_name }}">{{ $gaceta->original_name }}</small>
                    <a href="{{ asset('storage/' . $gaceta->path) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-1">Ver actual</a>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ url('/admin/gacetas/' . $gaceta->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">N° de gaceta</label>
                            <input type="text" name="numero" class="form-control" inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" value="{{ old('numero', $gaceta->numero) }}" required>
                            @error('numero')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $gaceta->fecha->format('Y-m-d')) }}" required>
                            @error('fecha')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reemplazar archivo <span class="text-muted">(opcional — PDF o imagen, máx. 20 MB)</span></label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        <a href="{{ url('/admin/gacetas') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
