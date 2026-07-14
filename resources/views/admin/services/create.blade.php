@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nuevo Servicio</h1>
    <form action="{{ url('/admin/servicios') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Título</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label>Contenido</label>
            <textarea name="content" class="form-control" rows="8"></textarea>
        </div>
        <div class="mb-3">
            <label>Icono</label>
            <input type="text" name="icon" class="form-control" placeholder="fa-solid fa-cogs">
        </div>
        <div class="mb-3">
            <label>URL Externa</label>
            <input type="url" name="external_url" class="form-control" placeholder="https://...">
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="0" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ url('/admin/servicios') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
