@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Miembro</h1>
    <form action="{{ url('/admin/estructura/' . $orgMember->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $orgMember->name) }}" required>
        </div>
        <div class="mb-3">
            <label>Cargo</label>
            <input type="text" name="position" class="form-control" value="{{ old('position', $orgMember->position) }}" required>
        </div>
        <div class="mb-3">
            <label>Foto (ruta)</label>
            <input type="text" name="photo" class="form-control" value="{{ old('photo', $orgMember->photo) }}">
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="4">{{ old('description', $orgMember->description) }}</textarea>
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $orgMember->order) }}" min="0">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="isActive" {{ $orgMember->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Activo</label>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ url('/admin/estructura') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
