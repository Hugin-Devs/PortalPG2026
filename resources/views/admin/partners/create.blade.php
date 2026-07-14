@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nuevo Ente Afiliado</h1>
    <form action="{{ url('/admin/entes') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Logo (ruta)</label>
            <input type="text" name="logo" class="form-control" placeholder="images/logo-ente.png">
        </div>
        <div class="mb-3">
            <label>Sitio Web</label>
            <input type="url" name="url" class="form-control" placeholder="https://...">
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="0" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ url('/admin/entes') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
