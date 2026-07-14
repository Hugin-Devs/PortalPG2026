@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Servicio</h1>
    <form action="{{ url('/admin/servicios/' . $service->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Título</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $service->title) }}" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control">{{ old('description', $service->description) }}</textarea>
        </div>
        <div class="mb-3">
            <label>Contenido</label>
            <textarea name="content" class="form-control" rows="8">{{ old('content', $service->content) }}</textarea>
        </div>
        <div class="mb-3">
            <label>Icono</label>
            <input type="text" name="icon" class="form-control" value="{{ old('icon', $service->icon) }}">
        </div>
        <div class="mb-3">
            <label>URL Externa</label>
            <input type="url" name="external_url" class="form-control" value="{{ old('external_url', $service->external_url) }}">
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $service->order) }}" min="0">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="isActive" {{ $service->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Activo</label>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ url('/admin/servicios') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
