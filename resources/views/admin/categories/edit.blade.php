@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Categoría</h1>
    <form action="{{ url('/admin/categorias/' . $category->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control">{{ old('description', $category->description) }}</textarea>
        </div>
        <div class="mb-3">
            <label>Icono</label>
            <input type="text" name="icon" class="form-control" value="{{ old('icon', $category->icon) }}">
        </div>
        <div class="mb-3">
            <label>Categoría Padre</label>
            <select name="parent_id" class="form-control">
                <option value="">Ninguna (raíz)</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $category->parent_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', $category->order) }}" min="0">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="isActive" {{ $category->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Activo</label>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ url('/admin/categorias') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
