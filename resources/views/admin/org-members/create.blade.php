@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nuevo Miembro</h1>
    <form action="{{ url('/admin/estructura') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Cargo</label>
            <input type="text" name="position" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Foto (ruta)</label>
            <input type="text" name="photo" class="form-control" placeholder="images/miembro.jpg">
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="4"></textarea>
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="0" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ url('/admin/estructura') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
