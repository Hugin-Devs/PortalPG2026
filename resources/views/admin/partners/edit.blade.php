@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Ente Afiliado</h1>
    <form action="{{ url('/admin/entes/' . $partner->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}" required>
        </div>
        <div class="mb-3">
            <label>Logo (ruta)</label>
            <input type="text" name="logo" class="form-control" value="{{ old('logo', $partner->logo) }}">
        </div>
        <div class="mb-3">
            <label>Sitio Web</label>
            <input type="url" name="url" class="form-control" value="{{ old('url', $partner->url) }}">
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
