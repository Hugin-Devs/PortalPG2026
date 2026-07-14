@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nueva Categoría</h1>
    <form action="{{ url('/admin/categorias') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label>Icono (clase FontAwesome)</label>
            <input type="text" name="icon" class="form-control" placeholder="fa-solid fa-newspaper">
        </div>
        <div class="mb-3">
            <label>Categoría Padre</label>
            <select name="parent_id" class="form-control">
                <option value="">Ninguna (raíz)</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="0" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ url('/admin/categorias') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
