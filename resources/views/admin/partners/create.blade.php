@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nuevo Ente Afiliado</h1>
    <form action="{{ url('/admin/entes') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Logo</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
            <div class="form-text">PNG, JPG, GIF, SVG o WebP · máx. 2 MB</div>
            @error('logo') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Sitio Web</label>
            <input type="url" name="url" class="form-control" value="{{ old('url') }}" placeholder="https://...">
            @error('url') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label>Orden</label>
            <input type="number" name="order" class="form-control" value="{{ old('order', 0) }}" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ url('/admin/entes') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
