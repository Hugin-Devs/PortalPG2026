@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Ente Afiliado</h1>
    <form action="{{ url('/admin/entes/' . $partner->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}" required>
            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $partner->description) }}</textarea>
            <div class="form-text">Texto corto que se muestra en el listado y en la página del ente</div>
            @error('description') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Logo</label>
            @if($partner->logo)
                <div class="mb-2">
                    <img src="{{ asset('storage/' . $partner->logo) }}" alt="{{ $partner->name }}" height="40" class="bg-white rounded p-1 border">
                </div>
            @endif
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
            <div class="form-text">PNG, JPG, GIF, SVG o WebP · máx. 2 MB — deja vacío para mantener el actual</div>
            @error('logo') <div class="text-danger">{{ $message }}</div> @enderror
            @if($partner->logo)
                <div class="form-check mt-2">
                    <input type="checkbox" name="remove_logo" value="1" class="form-check-input" id="removeLogo">
                    <label class="form-check-label" for="removeLogo">Quitar imagen actual</label>
                </div>
            @endif
        </div>
        <div class="mb-3">
            <label>Sitio Web</label>
            <input type="url" name="url" class="form-control" value="{{ old('url', $partner->url) }}" placeholder="https://...">
            @error('url') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $partner->order) }}" min="0">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="isActive" {{ $partner->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Activo</label>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ url('/admin/entes') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
