@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nueva Noticia</h1>
    <form action="{{ url('/admin/noticias') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label>Título</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Categoría</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Extracto</label>
            <textarea name="excerpt" class="form-control" required></textarea>
        </div>
        <div class="mb-3">
            <label>Contenido</label>
            @include('admin.news._editor', ['value' => old('content')])
            <div class="form-text">Las imágenes no se admiten dentro del texto (se eliminan al guardar); usa la imagen de portada y la galería.</div>
        </div>
        @include('admin.news._image-field')
        @include('admin.news._gallery-field')
        <div class="mb-3">
            <label>Estado</label>
            <select name="status" class="form-control" @if(! auth()->user()->hasPermission('news.approve')) disabled @endif>
                <option value="draft" selected>Borrador</option>
                <option value="published">Publicado</option>
            </select>
            @if(! auth()->user()->hasPermission('news.approve'))
            <small class="text-muted">Requiere aprobación (news.approve). Se guardará como borrador pendiente.</small>
            @endif
        </div>
        <div class="mb-3">
            <label>Fecha y hora de publicación</label>
            <input type="datetime-local" name="published_at" class="form-control"
                   @if(! auth()->user()->hasPermission('news.approve')) disabled @endif>
            <small class="text-muted">Opcional. Vacío = publicar inmediatamente. Use una fecha futura para programar (hora de Caracas). Solo aplica si el estado es Publicado.</small>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
@endsection